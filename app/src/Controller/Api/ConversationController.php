<?php

namespace App\Controller\Api;

use App\Entity\Candidate;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Workspace;
use App\Service\AiService;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/conversations', name: 'api_conversations_')]
class ConversationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AiService $aiService,
        private DocumentService $documentService,
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data        = json_decode($request->getContent(), true);
        $candidateId = $data['candidate_id'] ?? null;

        if (!$candidateId) {
            return $this->json(['error' => 'candidate_id is required'], 400);
        }

        $candidate = $this->em->getRepository(Candidate::class)->find($candidateId);
        if (!$candidate) {
            return $this->json(['error' => 'Candidate not found'], 404);
        }

        $workspace = $this->em->getRepository(Workspace::class)->findOneBy([]);

        $conversation = new Conversation();
        $conversation->setWorkspace($workspace);
        $conversation->setCandidate($candidate);
        $conversation->setTitle('Chat with ' . $candidate->getName());

        $this->em->persist($conversation);
        $this->em->flush();

        return $this->json([
            'id'           => $conversation->getId(),
            'title'        => $conversation->getTitle(),
            'candidate_id' => $candidate->getId(),
            'created_at'   => $conversation->getCreatedAt()->format('Y-m-d H:i:s'),
        ], 201);
    }

    #[Route('/{id}/messages', name: 'messages_list', methods: ['GET'])]
    public function messages(int $id): JsonResponse
    {
        $conversation = $this->em->getRepository(Conversation::class)->find($id);
        if (!$conversation) {
            return $this->json(['error' => 'Conversation not found'], 404);
        }

        $candidate = $conversation->getCandidate();

        $messages = array_map(fn(Message $m) => [
            'id'         => $m->getId(),
            'role'       => $m->getRole(),
            'content'    => $m->getContent(),
            'sources'    => $m->getSources(),
            'created_at' => $m->getCreatedAt()->format('Y-m-d H:i:s'),
        ], $conversation->getMessages()->toArray());

        return $this->json([
            'conversation_id' => $id,
            'candidate_id'    => $candidate?->getId(),
            'candidate_name'  => $candidate?->getName(),
            'candidate_status'=> $candidate?->getStatus(),
            'candidate_ai_score' => $candidate?->getAiScore(),
            'messages'        => $messages,
        ]);
    }

    #[Route('/{id}/messages', name: 'messages_create', methods: ['POST'])]
    public function sendMessage(int $id, Request $request): JsonResponse
    {
        $conversation = $this->em->getRepository(Conversation::class)->find($id);
        if (!$conversation) {
            return $this->json(['error' => 'Conversation not found'], 404);
        }

        $data    = json_decode($request->getContent(), true);
        $content = $data['message'] ?? '';

        if (empty($content)) {
            return $this->json(['error' => 'message is required'], 400);
        }

        // Save user message
        $userMessage = new Message();
        $userMessage->setConversation($conversation);
        $userMessage->setRole(Message::ROLE_USER);
        $userMessage->setContent($content);
        $this->em->persist($userMessage);

        // Get candidate CV for context
        $candidate = $conversation->getCandidate();
        $cvText = $candidate ? $this->documentService->getCandidateCvText($candidate) : null;

        if (!$cvText) {
            $aiReply = sprintf(
                "I don't have access to %s's CV document to answer your question. Please ensure the CV has been uploaded and processed.",
                $candidate?->getName() ?? 'the candidate'
            );
        } else {
            // Generate AI response using AiService
            try {
                $aiReply = $this->aiService->chatWithCv($cvText, $content);
            } catch (\Exception $e) {
                $aiReply = "I encountered an error while processing your question: " . $e->getMessage();
            }
        }

        $aiMessage = new Message();
        $aiMessage->setConversation($conversation);
        $aiMessage->setRole(Message::ROLE_ASSISTANT);
        $aiMessage->setContent($aiReply);
        $this->em->persist($aiMessage);

        $this->em->flush();

        return $this->json([
            'user_message' => [
                'id'      => $userMessage->getId(),
                'role'    => $userMessage->getRole(),
                'content' => $userMessage->getContent(),
            ],
            'ai_response' => [
                'id'      => $aiMessage->getId(),
                'role'    => $aiMessage->getRole(),
                'content' => $aiMessage->getContent(),
            ],
        ]);
    }
}
