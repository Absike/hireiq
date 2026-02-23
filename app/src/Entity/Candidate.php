<?php

namespace App\Entity;

use App\Repository\CandidateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CandidateRepository::class)]
#[ORM\Table(name: 'candidates')]
class Candidate
{
    public const STATUS_NEW        = 'new';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY      = 'ready';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_SHORTLIST  = 'shortlisted';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'candidates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne(targetEntity: JobPosition::class, inversedBy: 'candidates')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JobPosition $jobPosition = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = self::STATUS_NEW;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $aiScore = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aiSummary = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $aiExtractedData = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'candidate', targetEntity: Document::class, cascade: ['remove'])]
    private Collection $documents;

    #[ORM\OneToMany(mappedBy: 'candidate', targetEntity: Conversation::class, cascade: ['remove'])]
    private Collection $conversations;

    public function __construct()
    {
        $this->documents     = new ArrayCollection();
        $this->conversations = new ArrayCollection();
        $this->createdAt     = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): Workspace { return $this->workspace; }
    public function setWorkspace(Workspace $workspace): self { $this->workspace = $workspace; return $this; }
    public function getJobPosition(): ?JobPosition { return $this->jobPosition; }
    public function setJobPosition(?JobPosition $jobPosition): self { $this->jobPosition = $jobPosition; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getAiScore(): ?float { return $this->aiScore; }
    public function setAiScore(?float $aiScore): self { $this->aiScore = $aiScore; return $this; }
    public function getAiSummary(): ?string { return $this->aiSummary; }
    public function setAiSummary(?string $aiSummary): self { $this->aiSummary = $aiSummary; return $this; }
    public function getAiExtractedData(): ?array { return $this->aiExtractedData; }
    public function setAiExtractedData(?array $data): self { $this->aiExtractedData = $data; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getDocuments(): Collection { return $this->documents; }
    public function getConversations(): Collection { return $this->conversations; }
}
