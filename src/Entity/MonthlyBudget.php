<?php

namespace App\Entity;

use App\Repository\MonthlyBudgetRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: MonthlyBudgetRepository::class)]
class MonthlyBudget
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'monthlyBudget')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $amount = null; // string car DECIMAL Doctrine

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Budget $budget = null;

    public function getBudget(): ?Budget { return $this->budget; }
    public function setBudget(?Budget $budget): static { $this->budget = $budget; return $this; }
    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getAmount(): ?string { return $this->amount; }
    public function setAmount(string $amount): static { $this->amount = $amount; return $this; }
}