<?php

namespace App\Message;

use App\Entity\Candidate;
use App\Entity\Document;
use App\Entity\DocumentChunk;
use App\Service\AiService;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessDocumentHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AiService $aiService,
        private DocumentService $documentService,
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
            // Step 1 — Parse PDF and get text using DocumentService
            $text = $this->documentService->getDocumentText($document);

            if (empty(trim($text))) {
                throw new \RuntimeException('Could not extract text from PDF');
            }

            // Step 2 — Chunk text (for future RAG use)
            $chunks = $this->chunkText($text, 500);

            // Step 3 — Save chunks
            foreach ($chunks as $index => $chunkContent) {
                $chunk = new DocumentChunk();
                $chunk->setDocument($document);
                $chunk->setContent($chunkContent);
                $chunk->setChunkIndex($index);
                $chunk->setTokenCount(str_word_count($chunkContent));
                $this->em->persist($chunk);
            }

            // Step 4 — Extract candidate info using AiService
            $extracted = $this->aiService->extractCandidateInfo($text);

            // Update candidate
            $candidate = $document->getCandidate();
            if ($candidate) {
                if (!empty($extracted['name']))  $candidate->setName($extracted['name']);
                if (!empty($extracted['email'])) $candidate->setEmail($extracted['email']);
                
                // Set AI summary from professional summary if provided
                if (!empty($extracted['professional_summary'])) {
                    $candidate->setAiSummary($extracted['professional_summary']);
                }

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
