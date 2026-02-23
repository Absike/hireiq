<?php

namespace App\Entity;

use App\Repository\WorkspaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkspaceRepository::class)]
#[ORM\Table(name: 'workspaces')]
#[ORM\HasLifecycleCallbacks]
class Workspace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: JobPosition::class, cascade: ['remove'])]
    private Collection $jobPositions;

    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: Candidate::class, cascade: ['remove'])]
    private Collection $candidates;

    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: Document::class, cascade: ['remove'])]
    private Collection $documents;

    public function __construct()
    {
        $this->jobPositions = new ArrayCollection();
        $this->candidates   = new ArrayCollection();
        $this->documents    = new ArrayCollection();
        $this->createdAt    = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function generateSlug(): void
    {
        if (empty($this->slug)) {
            $this->slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $this->name)));
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getJobPositions(): Collection { return $this->jobPositions; }
    public function getCandidates(): Collection { return $this->candidates; }
    public function getDocuments(): Collection { return $this->documents; }
}
