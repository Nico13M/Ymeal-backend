<?php
namespace App\Entity;

use App\Repository\FrigoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FrigoRepository::class)]
class Frigo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'frigo', cascade: ['persist', 'remove'])]
    private ?User $user_frigo = null;

    /**
     * @var Collection<int, FrigoIngredient>
     */
    #[ORM\OneToMany(targetEntity: FrigoIngredient::class, mappedBy: 'frigo', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $frigoIngredients;

    public function __construct()
    {
        $this->frigoIngredients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserFrigo(): ?User
    {
        return $this->user_frigo;
    }

    public function setUserFrigo(?User $user_frigo): static
    {
        $this->user_frigo = $user_frigo;
        return $this;
    }

    /**
     * @return Collection<int, FrigoIngredient>
     */
    public function getFrigoIngredients(): Collection
    {
        return $this->frigoIngredients;
    }

    public function addFrigoIngredient(FrigoIngredient $frigoIngredient): static
    {
        if (!$this->frigoIngredients->contains($frigoIngredient)) {
            $this->frigoIngredients->add($frigoIngredient);
            $frigoIngredient->setFrigo($this);
        }
        return $this;
    }

    public function removeFrigoIngredient(FrigoIngredient $frigoIngredient): static
    {
        $this->frigoIngredients->removeElement($frigoIngredient);
        return $this;
    }

    public function hasIngredient(Ingredient $ingredient): bool
    {
        foreach ($this->frigoIngredients as $fi) {
            if ($fi->getIngredient() === $ingredient) {
                return true;
            }
        }
        return false;
    }

    public function getFrigoIngredientFor(Ingredient $ingredient): ?FrigoIngredient
    {
        foreach ($this->frigoIngredients as $fi) {
            if ($fi->getIngredient() === $ingredient) {
                return $fi;
            }
        }
        return null;
    }
}