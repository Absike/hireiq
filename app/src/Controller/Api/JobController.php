<?php

namespace App\Controller\Api;

use App\Entity\JobPosition;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/jobs', name: 'api_jobs_')]
class JobController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $jobs = $this->em->getRepository(JobPosition::class)->findAll();

        return $this->json(array_map(fn(JobPosition $j) => [
            'id'           => $j->getId(),
            'title'        => $j->getTitle(),
            'description'  => $j->getDescription(),
            'requirements' => $j->getRequirements(),
            'status'       => $j->getStatus(),
            'created_at'   => $j->getCreatedAt()->format('Y-m-d H:i:s'),
        ], $jobs));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['title']) || empty($data['description'])) {
            return $this->json(['error' => 'title and description are required'], 400);
        }

        $workspace = $this->em->getRepository(Workspace::class)->findOneBy([]);
        if (!$workspace) {
            return $this->json(['error' => 'No workspace found. Upload a CV first to create default workspace.'], 400);
        }

        $job = new JobPosition();
        $job->setWorkspace($workspace);
        $job->setTitle($data['title']);
        $job->setDescription($data['description']);
        $job->setRequirements($data['requirements'] ?? null);
        $job->setStatus(JobPosition::STATUS_OPEN);

        $this->em->persist($job);
        $this->em->flush();

        return $this->json([
            'message' => 'Job position created successfully.',
            'id'      => $job->getId(),
            'title'   => $job->getTitle(),
            'status'  => $job->getStatus(),
        ], 201);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $job = $this->em->getRepository(JobPosition::class)->find($id);
        if (!$job) {
            return $this->json(['error' => 'Job not found'], 404);
        }

        return $this->json([
            'id'           => $job->getId(),
            'title'        => $job->getTitle(),
            'description'  => $job->getDescription(),
            'requirements' => $job->getRequirements(),
            'status'       => $job->getStatus(),
            'candidates'   => $job->getCandidates()->count(),
            'created_at'   => $job->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $job = $this->em->getRepository(JobPosition::class)->find($id);
        if (!$job) {
            return $this->json(['error' => 'Job not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title']))        $job->setTitle($data['title']);
        if (isset($data['description']))  $job->setDescription($data['description']);
        if (isset($data['requirements'])) $job->setRequirements($data['requirements']);
        if (isset($data['status']))       $job->setStatus($data['status']);

        $this->em->flush();

        return $this->json(['message' => 'Job updated successfully.', 'id' => $job->getId()]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $job = $this->em->getRepository(JobPosition::class)->find($id);
        if (!$job) {
            return $this->json(['error' => 'Job not found'], 404);
        }

        $this->em->remove($job);
        $this->em->flush();

        return $this->json(['message' => 'Job deleted successfully.']);
    }
}
