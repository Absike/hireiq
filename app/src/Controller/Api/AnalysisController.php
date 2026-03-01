<?php

namespace App\Controller\Api;

use App\Entity\Candidate;
use App\Entity\Document;
use App\Entity\JobPosition;
use App\Service\AiService;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/analysis', name: 'api_analysis_')]
class AnalysisController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AiService $aiService,
        private DocumentService $documentService,
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

            $cvText = $this->documentService->getCandidateCvText($candidate);
            if ($cvText) {
                $analysis = $this->aiService->calculateScore($cvText, $job);
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

        $cvText = $this->documentService->getCandidateCvText($candidate);
        if (!$cvText) {
            return $this->json(['error' => 'No CV found for candidate'], 400);
        }

        $questions = $this->aiService->generateInterviewQuestions($cvText, $job);

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

                if ($forceRefresh || empty($extractedData)) {
                    $cvText = $this->documentService->getCandidateCvText($candidate);
                    if ($cvText) {
                        $extractedData = $this->aiService->extractCandidateInfo($cvText);
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
}
