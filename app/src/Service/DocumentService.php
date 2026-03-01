<?php

namespace App\Service;

use App\Entity\Candidate;
use App\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Smalot\PdfParser\Parser;

class DocumentService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function getCandidateCvText(Candidate $candidate): ?string
    {
        foreach ($candidate->getDocuments() as $document) {
            if ($document->getType() === Document::TYPE_CV) {
                return $this->getDocumentText($document);
            }
        }
        return null;
    }

    public function getDocumentText(Document $document): ?string
    {
        // If text is already extracted, return it
        if ($document->getTextContent()) {
            return $document->getTextContent();
        }

        // Otherwise, parse it if it exists
        $filePath = $document->getS3Path();
        if ($filePath && is_file($filePath)) {
            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($filePath);
                $text = $pdf->getText();
                
                if ($text) {
                    $document->setTextContent($text);
                    $this->em->flush();
                }
                
                return $text;
            } catch (\Throwable $e) {
                // Log or handle error
                return null;
            }
        }

        return null;
    }
}
