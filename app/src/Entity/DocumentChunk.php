<?php

namespace App\Entity;

use App\Repository\DocumentChunkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentChunkRepository::class)]
#[ORM\Table(name: 'document_chunks')]
class DocumentChunk
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'chunks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\Column(type: 'text')]
    private string $content;

    /**
     * Stored as JSON string in DB, represents a float[] vector (1536 dims for OpenAI)
     * We use a custom column definition for pgvector
     */
    #[ORM\Column(type: 'string', nullable: true, columnDefinition: 'vector(1536)')]
    private ?string $embedding = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $pageNumber = null;

    #[ORM\Column(type: 'integer')]
    private int $chunkIndex = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $tokenCount = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getDocument(): Document { return $this->document; }
    public function setDocument(Document $document): self { $this->document = $document; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }

    /**
     * Get embedding as float array
     */
    public function getEmbedding(): ?array
    {
        if ($this->embedding === null) return null;
        // pgvector stores as "[0.1,0.2,...]" string
        return json_decode(str_replace(['[', ']'], ['[', ']'], $this->embedding), true);
    }

    /**
     * Set embedding from float array — converts to pgvector string format
     */
    public function setEmbedding(?array $embedding): self
    {
        $this->embedding = $embedding !== null
            ? '[' . implode(',', $embedding) . ']'
            : null;
        return $this;
    }

    public function getEmbeddingRaw(): ?string { return $this->embedding; }

    public function getPageNumber(): ?int { return $this->pageNumber; }
    public function setPageNumber(?int $pageNumber): self { $this->pageNumber = $pageNumber; return $this; }

    public function getChunkIndex(): int { return $this->chunkIndex; }
    public function setChunkIndex(int $chunkIndex): self { $this->chunkIndex = $chunkIndex; return $this; }

    public function getTokenCount(): ?int { return $this->tokenCount; }
    public function setTokenCount(?int $tokenCount): self { $this->tokenCount = $tokenCount; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
