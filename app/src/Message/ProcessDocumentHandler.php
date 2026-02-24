<?php

namespace App\Message;

use App\Entity\Candidate;
use App\Entity\Document;
use App\Entity\DocumentChunk;
use Doctrine\ORM\EntityManagerInterface;
use Smalot\PdfParser\Parser;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class ProcessDocumentHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private string $groqKey,
        private HttpClientInterface $httpClient,
    ) {}

    public function __invoke(ProcessDocumentMessage $message): void
    {
        $document = $this->em->getRepository(Document::class)->find($message->documentId);
        if (!$document) return;

        $document->setStatus(Document::STATUS_PROCESSING);
        if ($document->getCandidate()) {
            $document->getCandidate()->setStatus(Candidate::STATUS_PROCESSING);
        }
        $this->em->flush();

        try {
            // Step 1 — Parse PDF
            $filePath = $document->getS3Path();
            $parser   = new Parser();
            $pdf      = $parser->parseFile($filePath);
            $text     = $pdf->getText();

            if (empty(trim($text))) {
                throw new \RuntimeException('Could not extract text from PDF');
            }

            // Step 2 — Chunk text
            $chunks = $this->chunkText($text, 500);

            // Step 3 — Save chunks (no embeddings for now, Groq doesn't have embeddings)
            foreach ($chunks as $index => $chunkContent) {
                $chunk = new DocumentChunk();
                $chunk->setDocument($document);
                $chunk->setContent($chunkContent);
                $chunk->setChunkIndex($index);
                $chunk->setTokenCount(str_word_count($chunkContent));
                $this->em->persist($chunk);
            }

            // Step 4 — Extract candidate info using Groq
            $extracted = $this->extractCandidateInfo($text);

            // Update candidate
            $candidate = $document->getCandidate();
            if ($candidate) {
                if (!empty($extracted['name']))  $candidate->setName($extracted['name']);
                if (!empty($extracted['email'])) $candidate->setEmail($extracted['email']);
                $candidate->setAiExtractedData($extracted);
                $candidate->setStatus(Candidate::STATUS_READY);
            }

            $document->setStatus(Document::STATUS_READY);
            $this->em->flush();

        } catch (\Throwable $e) {
            $document->setStatus(Document::STATUS_FAILED);
            if ($document->getCandidate()) {
                $document->getCandidate()->setStatus(Candidate::STATUS_NEW);
            }
            $this->em->flush();
            throw $e;
        }
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

        // If JSON is invalid, try to extract all fields with regex
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

    private function chunkText(string $text, int $chunkSize): array
    {
        $text    = preg_replace('/\s+/', ' ', trim($text));
        $words   = explode(' ', $text);
        $chunks  = [];
        $current = '';

        foreach ($words as $word) {
            if (strlen($current) + strlen($word) > $chunkSize && $current !== '') {
                $chunks[] = trim($current);
                $current  = '';
            }
            $current .= $word . ' ';
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}
