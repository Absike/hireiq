<?php

namespace App\Controller\Api;

use App\Entity\Candidate;
use App\Entity\Document;
use App\Entity\JobPosition;
use Doctrine\ORM\EntityManagerInterface;
use Smalot\PdfParser\Parser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/analysis', name: 'api_analysis_')]
class AnalysisController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private string $groqKey,
    ) {}

    #[Route('/rank', name: 'rank', methods: ['POST'])]
    public function rank(Request $request): JsonResponse
    {
        $data        = json_decode($request->getContent(), true);
        $jobId       = $data['job_id'] ?? null;
        $candidateIds = $data['candidate_ids'] ?? [];

        if (!$jobId || empty($candidateIds)) {
            return $this->json(['error' => 'job_id and candidate_ids are required'], 400);
        }

        $job = $this->em->getRepository(JobPosition::class)->find($jobId);
        if (!$job) {
            return $this->json(['error' => 'Job not found'], 404);
        }

        $results = [];
        foreach ($candidateIds as $candidateId) {
            $candidate = $this->em->getRepository(Candidate::class)->find($candidateId);
            if (!$candidate) continue;

            $cvText = $this->getCandidateCvText($candidate);
            if ($cvText) {
                $analysis = $this->analyzeCandidate($cvText, $job);
                $score = $analysis['score'];
                $summary = $analysis['summary'];
            } else {
                $score = null;
                $summary = 'No CV available for analysis';
            }

            $candidate->setAiScore($score);
            $candidate->setAiSummary($summary);
            $candidate->setJobPosition($job);

            $results[] = [
                'candidate_id'   => $candidate->getId(),
                'name'           => $candidate->getName(),
                'email'          => $candidate->getEmail(),
                'score'          => $score,
                'summary'        => $summary,
                'status'         => $candidate->getStatus(),
            ];
        }

        $this->em->flush();

        // Sort by score descending
        usort($results, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return $this->json([
            'job'        => ['id' => $job->getId(), 'title' => $job->getTitle()],
            'ranked'     => $results,
            'ai_powered' => true,
        ]);
    }

    #[Route('/interview-questions', name: 'interview_questions', methods: ['POST'])]
    public function interviewQuestions(Request $request): JsonResponse
    {
        $data        = json_decode($request->getContent(), true);
        $candidateId = $data['candidate_id'] ?? null;
        $jobId       = $data['job_id'] ?? null;

        if (!$candidateId) {
            return $this->json(['error' => 'candidate_id is required'], 400);
        }

        $candidate = $this->em->getRepository(Candidate::class)->find($candidateId);
        if (!$candidate) {
            return $this->json(['error' => 'Candidate not found'], 404);
        }

        $job = $jobId ? $this->em->getRepository(JobPosition::class)->find($jobId) : null;

        $cvText = $this->getCandidateCvText($candidate);
        if (!$cvText) {
            return $this->json(['error' => 'No CV found for candidate'], 400);
        }

        $questions = $this->generateQuestions($cvText, $job);

        return $this->json([
            'candidate'   => ['id' => $candidate->getId(), 'name' => $candidate->getName()],
            'job'         => $job ? ['id' => $job->getId(), 'title' => $job->getTitle()] : null,
            'questions'   => $questions,
            'ai_powered' => true,
        ]);
    }

    #[Route('/compare', name: 'compare', methods: ['POST'])]
    public function compare(Request $request): JsonResponse
    {
        $data         = json_decode($request->getContent(), true);
        $candidateIds = $data['candidate_ids'] ?? [];

        if (count($candidateIds) < 2) {
            return $this->json(['error' => 'At least 2 candidate_ids are required'], 400);
        }

        $candidates = [];
        foreach ($candidateIds as $id) {
            $candidate = $this->em->getRepository(Candidate::class)->find($id);
            if ($candidate) {
                // If no extracted data, try to extract it now
                $extractedData = $candidate->getAiExtractedData();
                if (!$extractedData) {
                    $cvText = $this->getCandidateCvText($candidate);
                    if ($cvText) {
                        $extractedData = $this->extractCandidateInfo($cvText);
                        if (!empty($extractedData)) {
                            $candidate->setAiExtractedData($extractedData);
                            $this->em->flush();
                        }
                    }
                }

                $candidates[] = [
                    'id'           => $candidate->getId(),
                    'name'         => $candidate->getName(),
                    'email'        => $candidate->getEmail(),
                    'status'       => $candidate->getStatus(),
                    'ai_score'     => $candidate->getAiScore(),
                    'extracted'    => $extractedData,
                ];
            }
        }

        return $this->json([
            'candidates' => $candidates,
            'ai_powered' => true,
        ]);
    }

    private function getCandidateCvText(Candidate $candidate): ?string
    {
        foreach ($candidate->getDocuments() as $document) {
            if ($document->getType() === Document::TYPE_CV && $document->getStatus() === 'ready') {
                $filePath = $document->getS3Path();
                if (file_exists($filePath)) {
                    $parser = new Parser();
                    $pdf = $parser->parseFile($filePath);
                    return $pdf->getText();
                }
            }
        }
        return null;
    }

    private function analyzeCandidate(string $cvText, JobPosition $job): array
    {
        $prompt = sprintf(
            'Evaluate this candidate for the position "%s".

Job Requirements: %s

Candidate CV:
%s

Respond with ONLY valid JSON: {"score": number (0-100), "summary": "2-3 sentence summary"}',
            $job->getTitle(),
            $job->getRequirements() ?? 'Not specified',
            substr($cvText, 0, 3000)
        );

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'auth_bearer' => $this->groqKey,
            'json' => [
                'model' => 'llama-3.1-8b-instant',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.3,
                'max_tokens' => 200,
            ],
        ]);

        $data = $response->toArray();
        $content = preg_replace('/```json\s*|\s*```/', '', $data['choices'][0]['message']['content'] ?? '');
        $result = json_decode(trim($content), true);

        return [
            'score' => $result['score'] ?? null,
            'summary' => $result['summary'] ?? 'Analysis completed',
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

        // If JSON is invalid or missing key fields, try to extract all fields with regex
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
                if (preg_match_all('/(?:Bachelor|Master|PhD|BS|MS|BA|MA|BSc|MSc|MSc)[^\.]*/i', $text, $matches)) {
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

    private function generateQuestions(?string $cvText, ?JobPosition $job): array
    {
        $prompt = sprintf(
            'Generate 5 interview questions for this candidate.%s

CV:
%s

Respond with ONLY a JSON array of strings: ["Q1?", "Q2?", ...]',
            $job ? sprintf(' The job is: %s', $job->getTitle()) : '',
            substr($cvText, 0, 3000)
        );

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'auth_bearer' => $this->groqKey,
            'json' => [
                'model' => 'llama-3.1-8b-instant',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.7,
                'max_tokens' => 250,
            ],
        ]);

        $data = $response->toArray();
        $content = preg_replace('/```json\s*|\s*```/', '', $data['choices'][0]['message']['content'] ?? '');
        $questions = json_decode(trim($content), true);

        return is_array($questions) ? $questions : [];
    }
}
