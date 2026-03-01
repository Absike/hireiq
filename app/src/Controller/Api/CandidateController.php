<?php

namespace App\Controller\Api;

use App\Entity\Candidate;
use App\Entity\Document;
use App\Entity\Workspace;
use App\Message\ProcessDocumentMessage;
use App\Service\AiService;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/candidates', name: 'api_candidates_')]
class CandidateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private AiService $aiService,
        private DocumentService $documentService,
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
            return $this->json(['error' => 'job_position_id is required'], 400);
        }

        $jobPosition = $this->em->getRepository(\App\Entity\JobPosition::class)->find($jobPositionId);
        if (!$jobPosition) {
            return $this->json(['error' => 'Job position not found'], 404);
        }

        $cvText = $this->documentService->getCandidateCvText($candidate);
        if (!$cvText) {
            return $this->json(['error' => 'CV not ready for scoring'], 400);
        }

        $result = $this->aiService->calculateScore($cvText, $jobPosition);

        $candidate->setAiScore($result['score']);
        $candidate->setAiSummary($result['summary']);
        $candidate->setJobPosition($jobPosition);
        $this->em->flush();

        return $this->json([
            'candidate_id' => $candidate->getId(),
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

        $cvText = $this->documentService->getCandidateCvText($candidate);
        if (!$cvText) {
            return $this->json(['error' => 'No CV document found'], 400);
        }

        $summary = $this->aiService->generateSummary($cvText);
        $candidate->setAiSummary($summary);
        $this->em->flush();

        return $this->json([
            'candidate_id' => $candidate->getId(),
            'summary' => $summary,
        ]);
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

        $cvText = $this->documentService->getCandidateCvText($candidate);
        if (!$cvText) {
            return $this->json(['error' => 'No CV document found'], 400);
        }

        $reply = $this->aiService->chatWithCv($cvText, $userMessage);

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

        $jobPosition = $jobPositionId ? $this->em->getRepository(\App\Entity\JobPosition::class)->find($jobPositionId) : null;

        $cvText = $this->documentService->getCandidateCvText($candidate);
        if (!$cvText) {
            return $this->json(['error' => 'No CV document found'], 400);
        }

        $questions = $this->aiService->generateInterviewQuestions($cvText, $jobPosition);

        return $this->json([
            'candidate_id' => $candidate->getId(),
            'questions' => $questions,
        ]);
    }
}
