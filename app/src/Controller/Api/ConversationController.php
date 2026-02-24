<?php

namespace App\Controller\Api;

use App\Entity\Candidate;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/conversations', name: 'api_conversations_')]
class ConversationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private string $groqKey,
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

        $messages = array_map(fn(Message $m) => [
            'id'         => $m->getId(),
            'role'       => $m->getRole(),
            'content'    => $m->getContent(),
            'sources'    => $m->getSources(),
            'created_at' => $m->getCreatedAt()->format('Y-m-d H:i:s'),
        ], $conversation->getMessages()->toArray());

        return $this->json([
            'conversation_id' => $id,
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
        $cvText = $this->getCandidateCvText($candidate);

        // Generate AI response using Grok
        $aiReply = $this->generateAiResponse($content, $cvText, $candidate?->getName());

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

    private function getCandidateCvText(?Candidate $candidate): ?string
    {
        if (!$candidate) return null;

        foreach ($candidate->getDocuments() as $document) {
            if ($document->getType() === 'cv' && $document->getStatus() === 'ready') {
                $filePath = $document->getS3Path();
                if ($filePath && file_exists($filePath)) {
                    try {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($filePath);
                        return $pdf->getText();
                    } catch (\Exception $e) {
                        return null;
                    }
                }
            }
        }
        return null;
    }

    private function generateAiResponse(string $question, ?string $cvText, ?string $candidateName): string
    {
        if (!$cvText) {
            return sprintf(
                "I don't have access to %s's CV document to answer your question. Please ensure the CV has been uploaded and processed.",
                $candidateName ?? 'the candidate'
            );
        }

        $systemPrompt = sprintf(
            'You are an AI assistant that answers questions about a candidate\'s CV. Use only the information from the CV provided. Be concise and professional. If the information is not in the CV, say so clearly. CV Content: %s',
            substr($cvText, 0, 8000)
        );

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'auth_bearer' => $this->groqKey,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $question],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1024,
                ],
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? 'Failed to get response';
        } catch (\Exception $e) {
            $errorDetail = $e->getMessage();
            // Try to get more details from the response
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                try {
                    $errorDetail .= ' - ' . $e->getResponse()->getContent(false);
                } catch (\Exception $e2) {}
            }
            return sprintf(
                "I encountered an error while processing your question: %s",
                $errorDetail
            );
        }
    }
}
