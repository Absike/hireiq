<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'messages')]
class Message
{
    public const ROLE_USER      = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_SYSTEM    = 'system';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    #[ORM\Column(type: 'string', length: 20)]
    private string $role = self::ROLE_USER;

    #[ORM\Column(type: 'text')]
    private string $content;

    /**
     * Sources: which document chunks were used to generate this answer
     * Stored as JSON: [{ chunk_id, document_name, page, excerpt }, ...]
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $sources = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getConversation(): Conversation { return $this->conversation; }
    public function setConversation(Conversation $conversation): self { $this->conversation = $conversation; return $this; }
    public function getRole(): string { return $this->role; }
    public function setRole(string $role): self { $this->role = $role; return $this; }
    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }
    public function getSources(): ?array { return $this->sources; }
    public function setSources(?array $sources): self { $this->sources = $sources; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
