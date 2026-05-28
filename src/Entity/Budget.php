<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BudgetRepository::class)]
class Budget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $key; // PETIT, MOYEN, LARGE

    #[ORM\Column(length: 100)]
    private string $label; // "Petit budget (< 100EUR/mois)"

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amount; // valeur représentative : 75, 150, 300

    public function getId(): ?int { return $this->id; }
    public function getKey(): string { return $this->key; }
    public function setKey(string $key): static { $this->key = $key; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): static { $this->label = $label; return $this; }
    public function getAmount(): string { return $this->amount; }
    public function setAmount(string $amount): static { $this->amount = $amount; return $this; }
}