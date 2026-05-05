<?php
namespace App\Entity;

use App\Repository\FrigoIngredientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FrigoIngredientRepository::class)]
#[ORM\Table(name: 'frigo_ingredient')]
#[ORM\UniqueConstraint(columns: ['frigo_id', 'ingredient_id'])]
class FrigoIngredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Frigo::class, inversedBy: 'frigoIngredients')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Frigo $frigo = null;

    #[ORM\ManyToOne(targetEntity: Ingredient::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Ingredient $ingredient = null;

    #[ORM\Column(type: 'float')]
    private float $quantity = 1;

    #[ORM\ManyToOne(targetEntity: Units::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Units $unit = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFrigo(): ?Frigo
    {
        return $this->frigo;
    }

    public function setFrigo(?Frigo $frigo): static
    {
        $this->frigo = $frigo;
        return $this;
    }

    public function getIngredient(): ?Ingredient
    {
        return $this->ingredient;
    }

    public function setIngredient(?Ingredient $ingredient): static
    {
        $this->ingredient = $ingredient;
        return $this;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnit(): ?Units
    {
        return $this->unit;
    }

    public function setUnit(?Units $unit): static
    {
        $this->unit = $unit;
        return $this;
    }
}