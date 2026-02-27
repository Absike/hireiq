<?php

namespace App\Controller\Api;

use App\Entity\Candidate;
use App\Entity\Document;
use App\Entity\Workspace;
use App\Message\ProcessDocumentMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/candidates', name: 'api_candidates_')]
class CandidateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private HttpClientInterface $httpClient,
        private string $groqKey,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $candidates = $this->em->getRepository(Candidate::class)->findAll();

        return $this->json(array_map(fn(Candidate $c) => [
            'id'         => $c->getId(),
            'name'       => $c->getName() ?? 'Unknown',
            'email'      => $c->getEmail(),
            'status'     => $c->getStatus(),
            'ai_score'   => $c->getAiScore(),
            'ai_summary' => $c->getAiSummary(),
            'ai_extracted_data' => $c->getAiExtractedData(),
            'job_position' => $c->getJobPosition() ? [
                'id' => $c->getJobPosition()->getId(),
                'title' => $c->getJobPosition()->getTitle(),
                'status' => $c->getJobPosition()->getStatus(),
            ] : null,
            'created_at' => $c->getCreatedAt()->format('Y-m-d H:i:s'),
        ], $candidates));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('cv');

        if (!$file) {
            return $this->json([
                'error' => 'No file uploaded. Use multipart/form-data with field name "cv".',
                'received_files' => array_keys($request->files->all()),
                'received_fields' => array_keys($request->request->all()),
            ], 400);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'pdf') {
            return $this->json(['error' => 'Only PDF files are supported'], 400);
        }

        // Get info BEFORE moving the file
        $originalName = $file->getClientOriginalName() ?? 'cv.pdf';
        $fileSize     = filesize($file->getRealPath());

        // Use filename as temporary name - will be updated after AI extraction
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $email = null;

        // Get or create default workspace
        $workspace = $this->em->getRepository(Workspace::class)->findOneBy([])
            ?? $this->createDefaultWorkspace();

        // Create candidate
        $candidate = new Candidate();
        $candidate->setWorkspace($workspace);
        $candidate->setName($name);
        $candidate->setEmail($email);
        $candidate->setStatus(Candidate::STATUS_PROCESSING);
        $this->em->persist($candidate);

        // Move file to project uploads dir (works in local and containerized envs)
        $uploadDir = rtrim((string) $this->getParameter('kernel.project_dir'), '/') . '/var/uploads';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return $this->json(['error' => 'Failed to create upload directory'], 500);
        }

        $filename = uniqid('cv_', true) . '.pdf';
        try {
            $file->move($uploadDir, $filename);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Failed to move uploaded file'], 500);
        }
        $finalPath = $uploadDir . '/' . $filename;

        // Create document record
        $document = new Document();
        $document->setWorkspace($workspace);
        $document->setCandidate($candidate);
        $document->setType(Document::TYPE_CV);
        $document->setFilename($originalName);
        $document->setS3Path($finalPath);
        $document->setMimeType('application/pdf');
        $document->setFileSize($fileSize ?: 0);
        $document->setStatus(Document::STATUS_UPLOADED);
        $this->em->persist($document);

        $this->em->flush();

        // Push to async queue for AI processing
        $this->bus->dispatch(new ProcessDocumentMessage($document->getId()));

        return $this->json([
            'id'         => $candidate->getId(),
            'name'       => $candidate->getName(),
            'email'      => $candidate->getEmail(),
            'status'     => $candidate->getStatus(),
            'ai_score'   => $candidate->getAiScore(),
            'ai_summary' => $candidate->getAiSummary(),
            'ai_extracted_data' => $candidate->getAiExtractedData(),
            'job_position' => null,
            'created_at' => $candidate->getCreatedAt()->format('Y-m-d H:i:s'),
            'document_id' => $document->getId(),
            'message'    => 'CV uploaded successfully. AI processing started.',
        ], 201);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $candidate = $this->em->getRepository(Candidate::class)->find($id);

        if (!$candidate) {
            return $this->json(['error' => 'Candidate not found'], 404);
        }

        return $this->json([
            'id'                => $candidate->getId(),
            'name'              => $candidate->getName(),
            'email'             => $candidate->getEmail(),
            'status'            => $candidate->getStatus(),
            'ai_score'          => $candidate->getAiScore(),
            'ai_summary'        => $candidate->getAiSummary(),
            'ai_extracted_data' => $candidate->getAiExtractedData(),
            'job_position'      => $candidate->getJobPosition() ? [
                'id' => $candidate->getJobPosition()->getId(),
                'title' => $candidate->getJobPosition()->getTitle(),
                'status' => $candidate->getJobPosition()->getStatus(),
            ] : null,
            'created_at'        => $candidate->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $candidate = $this->em->getRepository(Candidate::class)->find($id);

        if (!$candidate) {
            return $this->json(['error' => 'Candidate not found'], 404);
        }

        // Delete associated documents
        foreach ($candidate->getDocuments() as $document) {
            $filePath = $document->getS3Path();
            if ($filePath && file_exists($filePath)) {
                @unlink($filePath);
            }
            $this->em->remove($document);
        }

        $this->em->remove($candidate);
        $this->em->flush();

        return $this->json(['message' => 'Candidate deleted successfully']);
    }

    private function createDefaultWorkspace(): Workspace
    {
        $workspace = new Workspace();
        $workspace->setName('Default Workspace');
        $workspace->setSlug('default');
        $this->em->persist($workspace);
        return $workspace;
    }

    #[Route('/{id}/status', name: 'update_status', methods: ['PATCH'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $candidate = $this->em->getRepository(Candidate::class)->find($id);

        if (!$candidate) {
            return $this->json(['error' => 'Candidate not found'], 404);
        }

        $data   = json_decode($request->getContent(), true);
        $status = $data['status'] ?? null;

        $allowed = ['new', 'processing', 'ready', 'shortlisted', 'rejected'];
        if (!in_array($status, $allowed)) {
            return $this->json(['error' => 'Invalid status. Allowed: ' . implode(', ', $allowed)], 400);
        }

        $candidate->setStatus($status);
        $this->em->flush();

        return $this->json([
            'id'     => $candidate->getId(),
            'status' => $candidate->getStatus(),
            'message'=> 'Status updated successfully.',
        ]);
    }

    #[Route('/{id}/score', name: 'score', methods: ['POST'])]
    public function score(Request $request, int $id): JsonResponse
    {
        $candidate = $this->em->getRepository(Candidate::class)->find($id);

        if (!$candidate) {
            return $this->json(['error' => 'Candidate not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $jobPositionId = $data['job_position_id'] ?? null;

        if (!$jobPositionId) {
            return $this->json([
                'error' => 'job_position_id is required',
                'received' => $data,
            ], 400);
        }

        $jobPosition = $this->em->getRepository(\App\Entity\JobPosition::class)->find($jobPositionId);

        if (!$jobPosition) {
            return $this->json(['error' => 'Job position not found: ' . $jobPositionId], 404);
        }

        // Get candidate's CV text
        $cvText = $this->getCandidateCvText($candidate);
        if (!$cvText) {
            $docStatus = null;
            foreach ($candidate->getDocuments() as $doc) {
                if ($doc->getType() === Document::TYPE_CV) {
                    $docStatus = $doc->getStatus();
                }
            }
            return $this->json([
                'error' => 'CV not ready for scoring. Document status: ' . ($docStatus ?? 'no document'),
                'needs_processing' => true,
            ], 400);
        }

        // If no extracted data, extract it now
        if (!$candidate->getAiExtractedData()) {
            $extractedData = $this->extractCandidateInfo($cvText);
            if (!empty($extractedData)) {
                $candidate->setAiExtractedData($extractedData);
            }
        }

        // Calculate score using Groq
        $result = $this->calculateScore($cvText, $jobPosition);

        // Update candidate
        $candidate->setAiScore($result['score']);
        $candidate->setAiSummary($result['summary']);
        $candidate->setJobPosition($jobPosition);
        $this->em->flush();

        return $this->json([
            'candidate_id' => $candidate->getId(),
            'job_position_id' => $jobPosition->getId(),
            'job_position' => [
                'id' => $jobPosition->getId(),
                'title' => $jobPosition->getTitle(),
                'status' => $jobPosition->getStatus(),
            ],
            'score' => $result['score'],
            'summary' => $result['summary'],
        ]);
    }

    #[Route('/{id}/summarize', name: 'summarize', methods: ['POST'])]
    public function summarize(int $id): JsonResponse
    {
        $candidate = $this->em->getRepository(Candidate::class)->find($id);

        if (!$candidate) {
            return $this->json(['error' => 'Candidate not found'], 404);
        }

        // Get candidate's CV text
        $cvText = $this->getCandidateCvText($candidate);
        if (!$cvText) {
            return $this->json(['error' => 'No CV document found for candidate'], 400);
        }

        // Generate summary using Groq
        $summary = $this->generateSummary($cvText);

        // Update candidate
        $candidate->setAiSummary($summary);
        $this->em->flush();

        return $this->json([
            'candidate_id' => $candidate->getId(),
            'summary' => $summary,
        ]);
    }

    private function getCandidateCvText(Candidate $candidate): ?string
    {
        foreach ($candidate->getDocuments() as $document) {
            if ($document->getType() === Document::TYPE_CV && $document->getStatus() === 'ready') {
                $filePath = $document->getS3Path();
                if (file_exists($filePath)) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($filePath);
                    return $pdf->getText();
                }
            }
        }
        return null;
    }

    private function calculateScore(string $cvText, \App\Entity\JobPosition $jobPosition): array
    {
        $prompt = sprintf(
            'You are a hiring expert. Evaluate how well the candidate matches the job position.

Job Title: %s
Job Description: %s
Job Requirements: %s

Candidate CV:
%s

Provide a JSON response with:
- "score": A number from 0-100 representing how well the candidate matches (higher is better)
- "summary": A brief 2-3 sentence summary of why this candidate is or isn\'t a good fit

Return ONLY valid JSON, no explanation.',
            $jobPosition->getTitle(),
            $jobPosition->getDescription(),
            $jobPosition->getRequirements() ?? 'No specific requirements',
            substr($cvText, 0, 4000)
        );

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'auth_bearer' => $this->groqKey,
            'json' => [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
            ],
        ]);

        $data = $response->toArray();
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Clean JSON response
        $content = preg_replace('/```json\s*|\s*```/', '', $content);
        $result = json_decode(trim($content), true);

        return [
            'score' => $result['score'] ?? null,
            'summary' => $result['summary'] ?? 'Unable to generate summary',
        ];
    }

    private function extractCandidateInfo(string $text): array
    {
        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'auth_bearer' => $this->groqKey,
            'json' => [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => sprintf(
                        'Extract from this CV: name, email, phone, skills (array), years_experience, education (array), languages (array). Return ONLY valid JSON like {"name": "John", "email": "john@example.com", "phone": "+1234567890", "skills": ["PHP", "JavaScript"], "years_experience": 5, "education": ["BS CS"], "languages": ["English"]}. CV text: %s',
                        substr($text, 0, 6000)
                    )],
                ],
                'temperature' => 0.1,
            ],
        ]);

        $data = $response->toArray();
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Clean JSON response - remove markdown code blocks
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        $content = trim($content);

        $result = json_decode($content, true);

        // If JSON is invalid or missing skills, try to extract all fields with regex
        if (!$result || !isset($result['skills'])) {
            $result = $result ?? [];

            // Extract email
            if (!isset($result['email']) && preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
                $result['email'] = $matches[0];
            }

            // Extract name (first line that looks like a name)
            if (!isset($result['name']) && preg_match('/^([A-Z][a-z]+ [A-Z][a-z]+)/m', $text, $matches)) {
                $result['name'] = trim($matches[1]);
            }

            // Extract phone
            if (!isset($result['phone']) && preg_match('/\+(?:\d[\d\-\s]{8,}\d)/', $text, $matches)) {
                $result['phone'] = trim($matches[0]);
            }

            // Extract years of experience
            if (!isset($result['years_experience'])) {
                if (preg_match('/(\d+)\+?\s*(?:years?|yrs?)\s*(?:of)?\s*(?:experience|exp)/i', $text, $matches)) {
                    $result['years_experience'] = (int)$matches[1];
                } elseif (preg_match('/experience[:\s]+(\d+)/i', $text, $matches)) {
                    $result['years_experience'] = (int)$matches[1];
                }
            }

            // Extract skills (common programming languages and technologies)
            if (!isset($result['skills'])) {
                $techStack = ['PHP', 'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'Ruby', 'Go', 'Rust', 'Swift', 'Kotlin', 'React', 'Vue', 'Angular', 'Node.js', 'Laravel', 'Django', 'Spring', '.NET', 'AWS', 'Azure', 'GCP', 'Docker', 'Kubernetes', 'SQL', 'MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'GraphQL', 'REST', 'API', 'Git', 'Linux', 'HTML', 'CSS'];
                $foundSkills = [];
                foreach ($techStack as $tech) {
                    if (stripos($text, $tech) !== false) {
                        $foundSkills[] = $tech;
                    }
                }
                if (!empty($foundSkills)) {
                    $result['skills'] = array_unique($foundSkills);
                }
            }

            // Extract education
            if (!isset($result['education'])) {
                $education = [];
                if (preg_match_all('/(?:Bachelor|Master|PhD|BS|MS|BA|MA|BSc|MSc)[^\.]*/i', $text, $matches)) {
                    $education = array_slice($matches[0], 0, 3);
                }
                if (!empty($education)) {
                    $result['education'] = $education;
                }
            }

            // Extract languages
            if (!isset($result['languages'])) {
                $languages = [];
                if (preg_match_all('/(?:English|French|Spanish|German|Arabic|Chinese|Japanese|Korean|Portuguese|Italian|Russian|Hindi)[^\.]*/i', $text, $matches)) {
                    $languages = array_unique(array_map('ucfirst', array_map('strtolower', $matches[0])));
                }
                if (!empty($languages)) {
                    $result['languages'] = array_slice($languages, 0, 5);
                }
            }
        }

        return $result ?? [];
    }

    private function generateSummary(string $cvText): string
    {
        $prompt = sprintf(
            'You are a professional resume summarizer. Provide a concise 3-4 sentence summary of the candidate\'s profile based on their CV.

CV:
%s

Provide only the summary, no additional text.',
            substr($cvText, 0, 4000)
        );

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'auth_bearer' => $this->groqKey,
            'json' => [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ],
        ]);

        $data = $response->toArray();
        return $data['choices'][0]['message']['content'] ?? 'Unable to generate summary';
    }

    #[Route('/{id}/chat', name: 'chat', methods: ['POST'])]
    public function chat(Request $request, int $id): JsonResponse
    {
        $candidate = $this->em->getRepository(Candidate::class)->find($id);

        if (!$candidate) {
            return $this->json(['error' => 'Candidate not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? null;

        if (!$userMessage) {
            return $this->json(['error' => 'message is required'], 400);
        }

        $cvText = $this->getCandidateCvText($candidate);
        if (!$cvText) {
            return $this->json(['error' => 'No CV document found'], 400);
        }

        // Build conversation context
        $messages = [
            ['role' => 'system', 'content' => sprintf(
                'You are an AI assistant that answers questions about a candidate\'s CV. Use only the information from the CV provided. Be concise and professional. CV Content: %s',
                substr($cvText, 0, 3000)
            )],
            ['role' => 'user', 'content' => $userMessage],
        ];

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'auth_bearer' => $this->groqKey,
            'json' => [
                'model' => 'llama-3.1-8b-instant', // Smaller model for chat
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 500,
            ],
        ]);

        $result = $response->toArray();
        $reply = $result['choices'][0]['message']['content'] ?? 'Sorry, I could not generate a response.';

        return $this->json([
            'candidate_id' => $candidate->getId(),
            'reply' => $reply,
        ]);
    }

    #[Route('/{id}/interview-questions', name: 'interview_questions', methods: ['POST'])]
    public function interviewQuestions(Request $request, int $id): JsonResponse
    {
        $candidate = $this->em->getRepository(Candidate::class)->find($id);

        if (!$candidate) {
            return $this->json(['error' => 'Candidate not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $jobPositionId = $data['job_position_id'] ?? null;

        $jobPosition = null;
        if ($jobPositionId) {
            $jobPosition = $this->em->getRepository(\App\Entity\JobPosition::class)->find($jobPositionId);
        }

        $cvText = $this->getCandidateCvText($candidate);
        if (!$cvText) {
            return $this->json(['error' => 'No CV document found'], 400);
        }

        // Build prompt for interview questions
        $jobContext = $jobPosition
            ? sprintf('Job Title: %s\nJob Requirements: %s', $jobPosition->getTitle(), $jobPosition->getRequirements() ?? 'None')
            : 'No specific job position provided.';

        $prompt = sprintf(
            'Generate 5 tailored interview questions for this candidate based on their CV and the job position.

%s

Candidate CV:
%s

Return ONLY a JSON array of objects, each with "question" (string) and "reason" (string explaining why this question is relevant). No other text.',
            $jobContext,
            substr($cvText, 0, 3000)
        );

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'auth_bearer' => $this->groqKey,
            'json' => [
                'model' => 'llama-3.1-8b-instant',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 800,
            ],
        ]);

        $result = $response->toArray();
        $content = $result['choices'][0]['message']['content'] ?? '';

        // Clean JSON response
        $content = preg_replace('/```json\s*|\s*```/', '', $content);
        $questions = json_decode(trim($content), true);

        if (!is_array($questions)) {
            return $this->json(['error' => 'Failed to generate interview questions'], 500);
        }

        return $this->json([
            'candidate_id' => $candidate->getId(),
            'job_position_id' => $jobPosition?->getId(),
            'questions' => $questions,
        ]);
    }
}
