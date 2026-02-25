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
use Psr\Log\LoggerInterface;

#[Route('/api/analysis', name: 'api_analysis_')]
class AnalysisController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private string $groqKey,
        private ?LoggerInterface $logger = null,
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
        $forceRefresh = $data['force_refresh'] ?? false;

        if (count($candidateIds) < 2) {
            return $this->json(['error' => 'At least 2 candidate_ids are required'], 400);
        }

        $candidates = [];
        $hasUpdates = false;
        foreach ($candidateIds as $id) {
            $candidate = $this->em->getRepository(Candidate::class)->find($id);
            if ($candidate) {
                $extractedData = $candidate->getAiExtractedData();

                // Re-extract if: force refresh, or data is empty/missing critical fields
                $needsExtraction = $forceRefresh || empty($extractedData) ||
                    !isset($extractedData['years_experience']) ||
                    !isset($extractedData['education']) ||
                    !isset($extractedData['skills']);

                if ($needsExtraction) {
                    $cvText = $this->getCandidateCvText($candidate);
                    if ($cvText) {
                        $extractedData = $this->extractCandidateInfo($cvText);
                        if (!empty($extractedData)) {
                            $candidate->setAiExtractedData($extractedData);
                            $hasUpdates = true;
                        }
                    }
                }

                $candidates[] = [
                    'id'           => $candidate->getId(),
                    'name'         => $candidate->getName(),
                    'email'        => $candidate->getEmail(),
                    'status'       => $candidate->getStatus(),
                    'ai_score'     => $candidate->getAiScore(),
                    'extracted'    => $extractedData ?? [],
                ];
            }
        }

        if ($hasUpdates) {
            $this->em->flush();
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

                if (!$filePath) {
                    continue;
                }

                try {
                    $parser = new Parser();
                    if (filter_var($filePath, FILTER_VALIDATE_URL)) {
                        $content = @file_get_contents($filePath);
                        if ($content === false) {
                            $this->logger?->warning('CV download failed', [
                                'candidate' => $candidate->getId(),
                                'filePath' => $filePath,
                            ]);
                            continue;
                        }
                        $pdf = $parser->parseContent($content);
                    } elseif (is_file($filePath)) {
                        $pdf = $parser->parseFile($filePath);
                    } else {
                        continue;
                    }

                    $text = $pdf->getText();

                    if (empty($text) || strlen(trim($text)) < 50) {
                        $this->logger?->warning('CV extraction issue', [
                            'candidate' => $candidate->getId(),
                            'textLength' => strlen((string) $text),
                            'filePath' => $filePath,
                        ]);
                    }

                    return $text;
                } catch (\Throwable $e) {
                    $this->logger?->warning('CV parse failed', [
                        'candidate' => $candidate->getId(),
                        'filePath' => $filePath,
                        'error' => $e->getMessage(),
                    ]);
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

    /**
     * Main extraction function - uses AI + smart fallback
     */
    private function extractCandidateInfo(string $text): array
    {
        // Load the prompt template
        $promptPath = dirname(__DIR__, 2) . '/CVParser/PromptTemplate.txt';
        $promptTemplate = file_get_contents($promptPath);
        if ($promptTemplate === false) {
            $this->logger?->error('Failed to load CV parser prompt template');
            $promptTemplate = 'You are a CV parser. Return JSON.';
        }

        // Replace placeholder with actual CV text
        $prompt = str_replace('{CV_TEXT}', substr($text, 0, 15000), $promptTemplate);

        // Try AI extraction
        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'auth_bearer' => $this->groqKey,
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a very accurate, multilingual CV/resume parser. Your only job is to read the CV text and return clean, valid JSON — nothing else. Follow all rules strictly.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 4000,
                ],
            ]);

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';

            // Clean JSON - remove any markdown, explanations, or extra text
            $content = preg_replace('/^```(?:json)?\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
            $content = trim($content);

            $result = json_decode($content, true);

            // Validate we got a valid array
            if (!is_array($result)) {
                $this->logger?->warning('AI extraction did not return valid JSON object', ['content' => substr($content, 0, 300)]);
                $result = [];
            }
        } catch (\Exception $e) {
            $this->logger?->error('AI extraction failed', ['error' => $e->getMessage()]);
            $result = [];
        }

        // Merge with smart fallback - AI is primary, fallback fills gaps
        $fallback = $this->smartParseCV($text);

        // Only use fallback values if AI didn't provide them
        foreach ($fallback as $key => $fallbackValue) {
            if (!isset($result[$key]) || $result[$key] === '' || $result[$key] === null) {
                $result[$key] = $fallbackValue;
            }
        }

        // Map new schema to expected schema for backward compatibility
        $result = $this->mapExtractedDataSchema($result);

        // Ensure critical fields have fallback protection
        if (empty($result['years_experience'])) {
            $result['years_experience'] = $fallback['years_experience'] ?? 0;
        }
        if (empty($result['status'])) {
            $result['status'] = $fallback['status'] ?? 'unknown';
        }
        if (empty($result['education']) || !is_array($result['education'])) {
            $result['education'] = $fallback['education'] ?? [];
        }
        if (empty($result['skills']) || !is_array($result['skills'])) {
            $result['skills'] = $fallback['skills'] ?? [];
        }
        if (empty($result['languages']) || !is_array($result['languages'])) {
            $result['languages'] = $fallback['languages'] ?? [];
        }
        if (empty($result['work_experience']) || !is_array($result['work_experience'])) {
            $result['work_experience'] = $fallback['work_experience'] ?? [];
        }
        if (empty($result['professional_summary'])) {
            $result['professional_summary'] = $fallback['professional_summary'] ?? null;
        }
        if (empty($result['project_complexity'])) {
            $result['project_complexity'] = $fallback['project_complexity'] ?? null;
        }

        return $result;
    }

    /**
     * Map new AI schema to expected schema for backward compatibility
     */
    private function mapExtractedDataSchema(array $data): array
    {
        $result = $data;

        // Map full_name -> name
        if (isset($data['full_name']) && empty($data['name'])) {
            $result['name'] = $data['full_name'];
        }

        // Map experience (array of objects) -> work_experience
        if (isset($data['experience']) && is_array($data['experience'])) {
            $workExp = [];
            foreach ($data['experience'] as $exp) {
                $workExp[] = [
                    'title' => $exp['title'] ?? null,
                    'company' => $exp['organization'] ?? null,
                    'duration' => ($exp['start_date'] ?? '') . ' - ' . ($exp['end_date'] ?? ''),
                    'years' => null, // Will be calculated from dates if needed
                    'description' => $exp['description'] ?? null,
                ];
            }
            if (!empty($workExp)) {
                $result['work_experience'] = $workExp;
            }
        }

        // Map education array
        if (isset($data['education']) && is_array($data['education'])) {
            $education = [];
            foreach ($data['education'] as $edu) {
                if (is_string($edu)) {
                    $entry = trim($edu);
                } else {
                    $entry = trim(($edu['title'] ?? '') . ' ' . ($edu['organization'] ?? '') . ' ' . ($edu['location'] ?? ''));
                }
                if (!empty($entry)) {
                    $education[] = $entry;
                }
            }
            if (!empty($education)) {
                $result['education'] = array_unique($education);
            }
        }

        // Map skills (ensure it's a flat array)
        if (isset($data['skills']) && is_array($data['skills'])) {
            // If skills contain objects, extract the name/skill
            $skills = [];
            foreach ($data['skills'] as $skill) {
                if (is_string($skill)) {
                    $skills[] = $skill;
                } elseif (is_array($skill)) {
                    $skills[] = $skill['name'] ?? $skill['skill'] ?? $skill['value'] ?? '';
                }
            }
            $skills = array_values(array_filter($skills, fn($s) => $s !== ''));
            if (!empty($skills)) {
                $result['skills'] = array_unique($skills);
            }
        }

        // Map languages
        if (isset($data['languages']) && is_array($data['languages'])) {
            $langs = [];
            foreach ($data['languages'] as $lang) {
                if (is_string($lang)) {
                    $langs[] = $lang;
                } elseif (is_array($lang)) {
                    $langs[] = $lang['language'] ?? $lang['name'] ?? '';
                }
            }
            $langs = array_values(array_filter($langs, fn($l) => $l !== ''));
            if (!empty($langs)) {
                $result['languages'] = array_unique($langs);
            }
        }

        // Map certifications
        if (isset($data['certifications']) && is_array($data['certifications']) && empty($result['certifications'])) {
            $result['certifications'] = $data['certifications'];
        }

        // Map summary to professional_summary
        if (isset($data['summary']) && empty($result['professional_summary'])) {
            $result['professional_summary'] = $data['summary'];
        }

        return $result;
    }

    /**
     * Smart CV parsing with French/English support - handles "EXPERIENCES PROFESSIONNELLES" and "FORMATION" sections
     */
    private function smartParseCV(string $text): array
    {
        $result = [];

        // Extract email
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            $result['email'] = $matches[0];
        }

        // Extract phone
        if (preg_match('/(?:\+?\d{1,3}[-.\s]?)?\(?\d{2,4}\)?[-.\s]?\d{2,4}[-.\s]?\d{2,4}/', $text, $matches)) {
            $result['phone'] = trim($matches[0]);
        }

        // Extract name
        $lines = preg_split('/\r?\n/', $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^([A-ZÀ-ÖØ-Þ][a-zà-öø-ÿ]+[\s-]?[A-ZÀ-ÖØ-Þ][a-zà-öø-ÿ]+)/u', $line, $matches) && strlen($line) < 50) {
                $result['name'] = trim($matches[0]);
                break;
            }
        }

        // ============ EXPERIENCE CALCULATION - CRITICAL ============
        $totalYears = 0;
        $workExperiences = [];

        // Pattern 1: "04/2019 – 11/2021" (French format)
        if (preg_match_all('/(\d{2})\/(\d{4})\s*(?:-|–|—|to)+\s*(\d{2})?\/(\d{4}|present|current|aujourd|now)/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $startMonth = (int)$match[1];
                $startYear = (int)$match[2];
                $endMonth = !empty($match[3]) ? (int)$match[3] : date('m');
                $endYear = strtolower($match[4]) === 'present' || strtolower($match[4]) === 'current' || strtolower($match[4]) === 'aujourd' || strtolower($match[4]) === 'now'
                    ? (int)date('Y')
                    : (int)$match[4];

                $years = $endYear - $startYear;
                $months = $endMonth - $startMonth;
                $totalPeriod = $years + ($months / 12);
                if ($totalPeriod > 0 && $totalPeriod < 50) {
                    $totalYears += $totalPeriod;
                    $workExperiences[] = [
                        'duration' => $match[0],
                        'years' => round($totalPeriod, 1)
                    ];
                }
            }
        }

        // Pattern 2: "2019 - 2021"
        if (preg_match_all('/(20\d{2})\s*(?:-|–|—|to)+\s*(20\d{2}|present|current)/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $startYear = (int)$match[1];
                $endYear = strtolower($match[2]) === 'present' || strtolower($match[2]) === 'current'
                    ? (int)date('Y')
                    : (int)$match[2];
                $years = $endYear - $startYear;
                if ($years >= 0 && $years < 50) {
                    $alreadyCounted = false;
                    foreach ($workExperiences as $exp) {
                        if (strpos($exp['duration'], $match[1]) !== false) {
                            $alreadyCounted = true;
                            break;
                        }
                    }
                    if (!$alreadyCounted) {
                        $totalYears += $years;
                        $workExperiences[] = ['duration' => $match[0], 'years' => $years];
                    }
                }
            }
        }

        // Pattern 3: Explicit "5 ans" or "5 years"
        if ($totalYears == 0) {
            if (preg_match('/(\d+)\+?\s*(?:ans|years?|yrs?)\s*(?:d\'exp|experience|exp)?/i', $text, $matches)) {
                $totalYears = (int)$matches[1];
            }
        }

        if ($totalYears > 0) {
            $result['years_experience'] = min(round($totalYears, 1), 50);
        }

        // ============ EMPLOYMENT STATUS ============
        $status = 'unknown';

        if (preg_match('/(actuellement|currently|employed|CDI|CDD|en poste)/i', $text)) {
            $status = 'employed';
        } elseif (preg_match('/(recherche|seeking|looking for|cherche|en recherche)/i', $text)) {
            $status = 'seeking';
        } elseif (preg_match('/(freelance|indépendant|consultant|auto-entrepreneur)/i', $text)) {
            $status = 'freelance';
        } elseif (preg_match('/(student|étudiant|formation|university|école)/i', $text) && !preg_match('/former|ex-|previous/', $text)) {
            $status = 'student';
        }
        $result['status'] = $status;

        // ============ SKILLS ============
        $techStack = [
            'PHP', 'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'C++', 'Ruby', 'Go', 'Rust',
            'Swift', 'Kotlin', 'Scala', 'R', 'Perl', 'Shell', 'Bash',
            'React', 'Vue', 'Angular', 'Svelte', 'Next.js', 'Nuxt', 'Node.js', 'Express',
            'Laravel', 'Symfony', 'CodeIgniter', 'Django', 'Flask', 'FastAPI', 'Spring', '.NET',
            'AWS', 'Azure', 'GCP', 'Heroku', 'Docker', 'Kubernetes', 'Terraform',
            'MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'Elasticsearch', 'SQLite',
            'GraphQL', 'REST', 'API', 'gRPC',
            'Git', 'GitHub', 'GitLab',
            'Linux', 'Windows', 'Ubuntu', 'CentOS', 'macOS',
            'HTML', 'CSS', 'Sass', 'Tailwind',
            'Agile', 'Scrum', 'TDD', 'CI/CD', 'DevOps', 'Microservices'
        ];
        $foundSkills = [];
        foreach ($techStack as $tech) {
            if (preg_match('/\b' . preg_quote($tech, '/') . '\b/i', $text)) {
                $foundSkills[] = $tech;
            }
        }
        if (!empty($foundSkills)) {
            $result['skills'] = array_unique($foundSkills);
        }

        // ============ EDUCATION - Look for FORMATION section ============
        $education = [];
        $lines = preg_split('/\r?\n/', $text);
        $inFormationSection = false;

        $degreeKeywords = ['Bachelor', 'Master', 'PhD', 'Doctorate', 'BS', 'MS', 'MA', 'BA', 'BSc', 'MSc', 'MEng', 'BEng', 'Licence', 'Diplom', 'DUT', 'BTS', 'Doctorat', 'Maestría', 'Baccalauréat', 'BAC'];
        $institutionKeywords = ['University', 'Université', 'Ecole', 'School', 'Institute', 'College', 'MIT', 'INSA'];

        foreach ($lines as $line) {
            $line = trim($line);

            // Detect FORMATION section
            if (preg_match('/^(FORMATION|EDUCATION|ACADEMIC|SCOLARITÉ)/i', $line)) {
                $inFormationSection = true;
                continue;
            }
            // End of formation section
            if ($inFormationSection && preg_match('/^(EXPERIENCE|COMPÉTENCE|SKILLS|PROJETS)/i', $line)) {
                $inFormationSection = false;
            }

            // Skip contact info
            if (strpos($line, '@') !== false || preg_match('/^\+?\d{8,}/', $line)) {
                continue;
            }

            $hasDegree = false;
            foreach ($degreeKeywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $hasDegree = true;
                    break;
                }
            }

            $hasInstitution = false;
            foreach ($institutionKeywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $hasInstitution = true;
                    break;
                }
            }

            if ($hasDegree || $hasInstitution) {
                $year = '';
                if (preg_match('/(20\d{2}|19\d{2})/', $line, $ym)) {
                    $year = $ym[0];
                }
                if (strlen($line) > 10 && strlen($line) < 150) {
                    $educationEntry = $year ? "$line ($year)" : $line;
                    $education[] = $educationEntry;
                }
            }
        }

        if (!empty($education)) {
            $result['education'] = array_unique(array_slice($education, 0, 5));
        }

        // ============ SPOKEN LANGUAGES ============
        $spokenLanguages = ['English', 'French', 'Spanish', 'German', 'Arabic', 'Chinese', 'Japanese', 'Korean', 'Portuguese', 'Italian', 'Russian', 'Hindi', 'Dutch'];
        $foundLangs = [];
        foreach ($spokenLanguages as $lang) {
            if (stripos($text, $lang) !== false) {
                $foundLangs[] = $lang;
            }
        }
        if (!empty($foundLangs)) {
            $result['languages'] = array_unique(array_slice($foundLangs, 0, 5));
        }

        // ============ PROJECT COMPLEXITY ============
        $complexity = '';
        if (preg_match('/(lead|led|managed|team of|équipe|senior|architect)/i', $text)) {
            $complexity = 'Technical leadership and architecture decisions';
        }
        if (preg_match('/(built from scratch|created|developed|implémenté)/i', $text)) {
            $complexity = empty($complexity) ? 'Built applications from scratch' : $complexity . ', Built from scratch';
        }
        if (preg_match('/(microservices|distributed|architecture)/i', $text)) {
            $complexity = empty($complexity) ? 'Microservices/architecture work' : $complexity . ', Architecture';
        }
        $result['project_complexity'] = $complexity ?: 'Development work';

        // ============ ACHIEVEMENTS - look for metrics and accomplishments ============
        $achievements = [];
        $achievementKeywords = ['increased', 'reduced', 'improved', 'saved', 'grown', 'launched', 'delivered', 'optimized', 'automated'];
        $lines = preg_split('/\r?\n/', $text);
        $currentParagraph = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                // End of paragraph
                $found = false;
                foreach ($achievementKeywords as $keyword) {
                    if (preg_match('/\b' . $keyword . '\b/i', $currentParagraph)) {
                        $found = true;
                        break;
                    }
                }
                // Also look for metrics (numbers with % or $)
                if (preg_match('/\d+%|\$\d+|€\d+/', $currentParagraph)) {
                    $found = true;
                }
                if ($found && strlen($currentParagraph) > 20 && strlen($currentParagraph) < 300) {
                    $achievements[] = trim($currentParagraph);
                }
                $currentParagraph = '';
                continue;
            }
            if (strlen($currentParagraph) > 0) {
                $currentParagraph .= ' ';
            }
            $currentParagraph .= $line;
        }
        // Check last paragraph
        if (strlen($currentParagraph) > 20) {
            foreach ($achievementKeywords as $keyword) {
                if (preg_match('/\b' . $keyword . '\b/i', $currentParagraph)) {
                    $achievements[] = trim($currentParagraph);
                    break;
                }
            }
        }
        if (!empty($achievements)) {
            $result['achievements'] = array_slice(array_unique($achievements), 0, 5);
        }

        // ============ ENGINEERING PRACTICES ============
        $engineeringPractices = [];
        $epKeywords = ['TDD', 'CI/CD', 'Agile', 'Scrum', 'DevOps', 'Microservices', 'Docker', 'Kubernetes', 'AWS', 'Azure', 'Git', 'GitHub', 'GitLab', 'Testing', 'Automated', 'CI', 'CD'];
        foreach ($epKeywords as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $text)) {
                $engineeringPractices[] = $keyword;
            }
        }
        if (!empty($engineeringPractices)) {
            $result['engineering_practices'] = array_unique($engineeringPractices);
        }

        // ============ SIDE PROJECTS ============
        $sideProjects = [];
        $projectKeywords = ['personal project', 'side project', 'github', 'portfolio', 'Open Source', '开源', 'hobby project'];
        $lines = preg_split('/\r?\n/', $text);
        foreach ($lines as $line) {
            $line = trim($line);
            foreach ($projectKeywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    // Check if line has a project name (not too long, not just keywords)
                    $cleanLine = preg_replace('/' . preg_quote($keyword, '/') . '/i', '', $line);
                    $cleanLine = trim($cleanLine);
                    if (strlen($cleanLine) > 3 && strlen($cleanLine) < 100) {
                        $sideProjects[] = $cleanLine;
                    }
                    break;
                }
            }
        }
        // Also look for GitHub links
        if (preg_match_all('/github\.com\/([a-zA-Z0-9_-]+)/', $text, $ghMatches)) {
            foreach ($ghMatches[1] as $repo) {
                $sideProjects[] = 'GitHub: ' . $repo;
            }
        }
        if (!empty($sideProjects)) {
            $result['side_projects'] = array_unique(array_slice($sideProjects, 0, 5));
        }

        // ============ CAREER PROGRESSION ============
        $careerProgression = '';
        // Look for promotion indicators
        if (preg_match('/(promoted|senior|lead|manager)/i', $text)) {
            $careerProgression = 'Shows career progression with promotions';
        }
        if (preg_match('/(founded|started|created company)/i', $text)) {
            $careerProgression = empty($careerProgression) ? 'Entrepreneurial experience' : $careerProgression . ', Entrepreneurial';
        }
        if (preg_match('/(freelance|contract|consultant)/i', $text)) {
            $careerProgression = empty($careerProgression) ? 'Freelance/contract work' : $careerProgression . ', Freelance/Contract';
        }
        $result['career_progression'] = $careerProgression ?: 'Stable career path';

        // ============ PROFESSIONAL SUMMARY ============
        $paragraphs = preg_split('/\r?\n\r?\n/', $text);
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (strlen($para) > 80 && strlen($para) < 600) {
                if (strpos($para, '@') === false && !preg_match('/^(Bachelor|Master|PhD|Skills|Experience|Formation)/i', $para)) {
                    $result['professional_summary'] = $para;
                    break;
                }
            }
        }

        return $result;
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
