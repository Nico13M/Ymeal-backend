<?php

namespace App\Entity;

use App\Repository\IngredientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
// Import indispensable pour le Slug automatique
use Gedmo\Mapping\Annotation as Gedmo;
// Import indispensable pour les dates (created_at, updated_at)
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: IngredientRepository::class)]
class Ingredient
{
    // Ajoute les dates automatiques (created_at, updated_at)
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])] // Génère le slug automatiquement basé sur le nom
    private ?string $slug = null;

    /**
     * @var Collection<int, RecipeIngredient>
     */
    #[ORM\OneToMany(targetEntity: RecipeIngredient::class, mappedBy: 'ingredient')]
    private Collection $recipeIngredients;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'user_ingredients_blacklist')]
    private Collection $users;

    /**
     * @var Collection<int, Frigo>
     */
    /**
     * @var Collection<int, FrigoIngredient>
     */
    #[ORM\OneToMany(targetEntity: FrigoIngredient::class, mappedBy: 'ingredient', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $frigoIngredients;

    #[ORM\ManyToOne(inversedBy: 'ingredients')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Units $units = null;

      #[ORM\Column(length: 255, nullable: true)]
    private string $codeOff;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $generic_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $categories_tags = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $allergens = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nutriscore_grade = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image_small_url = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $energy_100g = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $fat_100g = null;

    #[ORM\Column(name: 'saturated_fat_100g', type: 'float', nullable: true)]
    private ?float $saturated_fat_100g = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $carbohydrates_100g = null;

    public function __construct()
    {
        $this->recipeIngredients = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->frigoIngredients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return Collection<int, RecipeIngredient>
     */
    public function getRecipeIngredients(): Collection
    {
        return $this->recipeIngredients;
    }

    public function addRecipeIngredient(RecipeIngredient $recipeIngredient): static
    {
        if (!$this->recipeIngredients->contains($recipeIngredient)) {
            $this->recipeIngredients->add($recipeIngredient);
            $recipeIngredient->setIngredient($this);
        }

        return $this;
    }

    public function removeRecipeIngredient(RecipeIngredient $recipeIngredient): static
    {
        if ($this->recipeIngredients->removeElement($recipeIngredient)) {
            // set the owning side to null (unless already changed)
            if ($recipeIngredient->getIngredient() === $this) {
                $recipeIngredient->setIngredient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->addUserIngredientsBlacklist($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            $user->removeUserIngredientsBlacklist($this);
        }

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
            $frigoIngredient->setIngredient($this);
        }

        return $this;
    }

    public function removeFrigoIngredient(FrigoIngredient $frigoIngredient): static
    {
        if ($this->frigoIngredients->removeElement($frigoIngredient)) {
            if ($frigoIngredient->getIngredient() === $this) {
                $frigoIngredient->setIngredient(null);
            }
        }

        return $this;
    }

    public function getUnits(): ?Units
    {
        return $this->units;
    }

    public function setUnits(?Units $units): static
    {
        $this->units = $units;

        return $this;
    }

    public function getCodeOff(): string 
    {
        return $this->codeOff; 
    }
    
    public function setCodeOff(string $codeOff): self 
    { 
        $this->codeOff = $codeOff; 
        return $this; 
    }


    public function getAllergens(): ?string
    {
        return $this->allergens;
    }

    public function setAllergens(?string $allergens): static
    {
        $this->allergens = $allergens;

        return $this;
    }

    public function getGenericName(): ?string
    {
        return $this->generic_name;
    }

    public function setGenericName(?string $generic_name): static
    {
        $this->generic_name = $generic_name;

        return $this;
    }

    public function getCategoriesTags(): ?string
    {
        return $this->categories_tags;
    }

    public function setCategoriesTags(?string $categories_tags): static
    {
        $this->categories_tags = $categories_tags;

        return $this;
    }

    public function getNutriscoreGrade(): ?string
    {
        return $this->nutriscore_grade;
    }

    public function setNutriscoreGrade(?string $nutriscore_grade): static
    {
        $this->nutriscore_grade = $nutriscore_grade;

        return $this;
    }

    public function getImageSmallUrl(): ?string
    {
        return $this->image_small_url;
    }

    public function setImageSmallUrl(?string $image_small_url): static
    {
        $this->image_small_url = $image_small_url;

        return $this;
    }

    public function getEnergy100g(): ?float
    {
        return $this->energy_100g;
    }

    public function setEnergy100g(?float $energy_100g): static
    {
        $this->energy_100g = $energy_100g;

        return $this;
    }

    public function getFat100g(): ?float
    {
        return $this->fat_100g;
    }

    public function setFat100g(?float $fat_100g): static
    {
        $this->fat_100g = $fat_100g;

        return $this;
    }

    public function getSaturatedFat100g(): ?float
    {
        return $this->saturated_fat_100g;
    }

    public function setSaturatedFat100g(?float $saturated_fat_100g): static
    {
        $this->saturated_fat_100g = $saturated_fat_100g;

        return $this;
    }

    public function getCarbohydrates100g(): ?float
    {
        return $this->carbohydrates_100g;
    }

    public function setCarbohydrates100g(?float $carbohydrates_100g): static
    {
        $this->carbohydrates_100g = $carbohydrates_100g;

        return $this;
    }
}