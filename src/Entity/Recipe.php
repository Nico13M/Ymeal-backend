<?php


namespace App\Entity;

use App\Repository\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use App\Entity\User; 
use App\Entity\RecipeIngredient;

#[ORM\Entity(repositoryClass: RecipeRepository::class)]
class Recipe
{
    use TimestampableEntity;

    // ============= IDENTIFIANTS =============

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ============= INFORMATIONS DE BASE =============

    #[ORM\Column(length: 255)]
    private ?string $name = null; // Nom de la recette

    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])] // Auto-généré à partir du nom
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null; // Description détaillée

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null; // URL de l'image

    // ============= DONNÉES DE CUISSON =============

    #[ORM\Column]
    private ?int $servings = null; // Nombre de portions

    #[ORM\Column(nullable: true)]
    private ?int $duration = null; // Temps de cuisson en minutes

    #[ORM\Column(nullable: true)]
    private ?int $time = null; // Temps de préparation en minutes

    // ============= CATÉGORISATION =============

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $difficulty = null; // Niveau: easy, medium, hard

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $dishType = null; // Type: pasta, salad, soup, dessert, etc.

    // ============= VISIBILITÉ =============

    #[ORM\Column]
    private ?bool $is_public = null; // Visible publiquement ou non

    // ============= RELATIONS =============

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null; // Auteur de la recette

    /**
     * @var Collection<int, RecipeIngredient>
     */
    #[ORM\OneToMany(mappedBy: 'recipe', targetEntity: RecipeIngredient::class, orphanRemoval: true)]
    private Collection $recipeIngredients; // Ingrédients de la recette

    /**
     * @var Collection<int, Diet>
     */
    #[ORM\ManyToMany(targetEntity: Diet::class, inversedBy: 'recipes')]
    private Collection $diets_has_recipe; // Régimes alimentaires compatibles

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'user_recipe_preferences')]
    private Collection $user_recipe_preferences; // Utilisateurs qui ont mis en favoris

    public function __construct()
    {
        $this->recipeIngredients = new ArrayCollection();
        $this->diets_has_recipe = new ArrayCollection();
        $this->user_recipe_preferences = new ArrayCollection();
    }

    // ============= GETTERS & SETTERS =============

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }
    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }
    public function getServings(): ?int { return $this->servings; }
    public function setServings(int $servings): static { $this->servings = $servings; return $this; }
    public function getDuration(): ?int { return $this->duration; }
    public function setDuration(?int $duration): static { $this->duration = $duration; return $this; }
    public function getDifficulty(): ?string { return $this->difficulty; }
    public function setDifficulty(?string $difficulty): static { $this->difficulty = $difficulty; return $this; }
    public function getTime(): ?int { return $this->time; }
    public function setTime(?int $time): static { $this->time = $time; return $this; }
    public function getDishType(): ?string { return $this->dishType; }
    public function setDishType(?string $dishType): static { $this->dishType = $dishType; return $this; }
    public function isPublic(): ?bool { return $this->is_public; }
    public function setIsPublic(bool $is_public): static { $this->is_public = $is_public; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    // ============= GESTION DES INGRÉDIENTS =============

    public function getRecipeIngredients(): Collection { return $this->recipeIngredients; }
    public function addRecipeIngredient(RecipeIngredient $recipeIngredient): static
    {
        if (!$this->recipeIngredients->contains($recipeIngredient)) {
            $this->recipeIngredients->add($recipeIngredient);
            $recipeIngredient->setRecipe($this);
        }
        return $this;
    }
    public function removeRecipeIngredient(RecipeIngredient $recipeIngredient): static
    {
        if ($this->recipeIngredients->removeElement($recipeIngredient)) {
            if ($recipeIngredient->getRecipe() === $this) {
                // Relation bidirectionnelle
            }
        }
        return $this;
    }

    // ============= GESTION DES RÉGIMES =============

    public function getDietsHasRecipe(): Collection { return $this->diets_has_recipe; }
    public function addDietsHasRecipe(Diet $dietsHasRecipe): static
    {
        if (!$this->diets_has_recipe->contains($dietsHasRecipe)) {
            $this->diets_has_recipe->add($dietsHasRecipe);
        }
        return $this;
    }
    public function removeDietsHasRecipe(Diet $dietsHasRecipe): static
    {
        $this->diets_has_recipe->removeElement($dietsHasRecipe);
        return $this;
    }

    // ============= GESTION DES FAVORIS =============

    public function getUserRecipePreferences(): Collection { return $this->user_recipe_preferences; }
    public function addUserRecipePreference(User $user_recipe_preference): static
    {
        if (!$this->user_recipe_preferences->contains($user_recipe_preference)) {
            $this->user_recipe_preferences->add($user_recipe_preference);
        }
        return $this;
    }
    public function removeUserRecipePreference(User $user_recipe_preference): static
    {
        $this->user_recipe_preferences->removeElement($user_recipe_preference);
        return $this;
    }
}