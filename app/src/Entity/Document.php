<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'documents')]
class Document
{
    public const TYPE_CV               = 'cv';
    public const TYPE_JOB_DESCRIPTION  = 'job_description';
    public const TYPE_OTHER            = 'other';

    public const STATUS_UPLOADED    = 'uploaded';
    public const STATUS_PROCESSING  = 'processing';
    public const STATUS_READY       = 'ready';
    public const STATUS_FAILED      = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne(targetEntity: Candidate::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Candidate $candidate = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type = self::TYPE_CV;

    #[ORM\Column(type: 'string', length: 255)]
    private string $filename;

    #[ORM\Column(type: 'string', length: 500)]
    private string $s3Path;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = self::STATUS_UPLOADED;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $textContent = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'document', targetEntity: DocumentChunk::class, cascade: ['remove'])]
    private Collection $chunks;

    public function __construct()
    {
        $this->chunks    = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function setWorkspace(Workspace $workspace): self { $this->workspace = $workspace; return $this; }
    public function getCandidate(): ?Candidate { return $this->candidate; }
    public function setCandidate(?Candidate $candidate): self { $this->candidate = $candidate; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $filename): self { $this->filename = $filename; return $this; }
    public function getS3Path(): string { return $this->s3Path; }
    public function setS3Path(string $s3Path): self { $this->s3Path = $s3Path; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $mimeType): self { $this->mimeType = $mimeType; return $this; }
    public function getFileSize(): ?int { return $this->fileSize; }
    public function setFileSize(?int $fileSize): self { $this->fileSize = $fileSize; return $this; }
    public function getTextContent(): ?string { return $this->textContent; }
    public function setTextContent(?string $textContent): self { $this->textContent = $textContent; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getChunks(): Collection { return $this->chunks; }
}
