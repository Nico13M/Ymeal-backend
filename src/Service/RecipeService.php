<?php

namespace App\Service;

use App\Entity\Recipe;
use App\Entity\User;
use App\Repository\RatingRepository;

class RecipeService
{
    public function __construct(
        private RatingRepository $ratingRepository
    ) {}
    /**
     * 🍽️ Sérialise une recette COMPLÈTE avec tous ses détails (pour GET /{id})
     * Utilisé pour afficher les détails d'une recette
     */
    public function serializeRecipe(Recipe $recipe, ?User $user = null): array
    {
        return [
            // ============= INFORMATIONS DE BASE =============
            'id' => $recipe->getId(),
            'name' => $recipe->getName(),
            'slug' => $recipe->getSlug(),
            'description' => $recipe->getDescription(),
            'image' => $recipe->getImage(),
            'servings' => $recipe->getServings(),

            // ============= TIMING DE CUISSON =============
            'timing' => [
                'duration' => $recipe->getDuration(), // Cuisson
                'prep_time' => $recipe->getTime(), // Préparation
                'total_time' => ($recipe->getDuration() ?? 0) + ($recipe->getTime() ?? 0), // Total
            ],

            // ============= CATÉGORISATION =============
            'difficulty' => $recipe->getDifficulty(), // easy, medium, hard
            'dish_type' => $recipe->getDishType(), // pasta, salad, etc.
            'is_public' => $recipe->isPublic(), // Visibilité publique

            // ============= ÉTAPES DE PRÉPARATION =============
            'steps' => $recipe->getSteps() ?? [], // Étapes de préparation

            // ============= TIMESTAMPS =============
            'timestamps' => [
                'created_at' => $recipe->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $recipe->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ],

            // ============= AUTEUR =============
            'author' => $recipe->getUser() ? [
                'id' => $recipe->getUser()->getId(),
                'name' => $recipe->getUser()->getFirstname() . ' ' . $recipe->getUser()->getLastname(),
                'email' => $recipe->getUser()->getEmail(),
            ] : null,

            // ============= CONTENU NUTRITIONNEL =============
            'nutrition' => [
                'diets' => array_map(fn($diet) => [
                    'id' => $diet->getId(),
                    'name' => $diet->getName(),
                ], $recipe->getDietsHasRecipe()->toArray()),
                
                'ingredients' => array_map(fn($recipeIngredient) => [
                    'id' => $recipeIngredient->getIngredient()->getId(),
                    'name' => $recipeIngredient->getIngredient()->getName(),
                    'quantity' => $recipeIngredient->getQuantity(),
                    'unit' => $recipeIngredient->getUnit() ? [
                        'id' => $recipeIngredient->getUnit()->getId(),
                        'name' => $recipeIngredient->getUnit()->getName(),
                        'symbol' => $recipeIngredient->getUnit()->getSymbol(),
                    ] : null,
                ], $recipe->getRecipeIngredients()->toArray()),
            ],

            // ============= ENGAGEMENT =============
            'engagement' => [
                'favorites_count' => $recipe->getUserRecipePreferences()?->count() ?? 0, // Nombre de favoris
                'is_favorited' => $user ? $user->getUserRecipePreferences()->contains($recipe) : false, // Est en favoris par l'utilisateur
            ],

            // ============= RATINGS & COMMENTAIRES =============
            'ratings' => $this->serializeRecipeRatings($recipe),
        ];
    }

    /**
     * 📊 Sérialise les ratings d'une recette avec stats et commentaires
     */
    private function serializeRecipeRatings(Recipe $recipe): array
    {
        $stats = $this->ratingRepository->getStatsForRecipe($recipe);
        $ratingList = $this->ratingRepository->findByRecipeWithUser($recipe);

        return [
            'stats' => $stats, // Moyenne, count, distribution
            'comments' => array_map(function($rating) {
                return [
                    'id' => $rating->getId(),
                    'rating' => $rating->getRating(),
                    'comment' => $rating->getComment(),
                    'user' => [
                        'id' => $rating->getUser()->getId(),
                        'name' => $rating->getUser()->getFirstname() . ' ' . $rating->getUser()->getLastname(),
                    ],
                    'created_at' => $rating->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updated_at' => $rating->getUpdatedAt()->format('Y-m-d H:i:s'),
                ];
            }, $ratingList),
        ];
    }

    /**
     * 📋 Sérialise une recette en version MINIMALISTE (pour les listes /search, /favorites)
     * Utilisé pour les listes - données réduites pour performancer
     */
    public function serializeRecipeMinimal(Recipe $recipe): array
    {
        return [
            'id' => $recipe->getId(),
            'name' => $recipe->getName(),
            'slug' => $recipe->getSlug(),
            'image' => $recipe->getImage(),
            'description' => $recipe->getDescription(),
            'servings' => $recipe->getServings(),
            'difficulty' => $recipe->getDifficulty(),
             'timestamps' => [
                'created_at' => $recipe->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $recipe->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ],
            'timing' => [
                'duration' => $recipe->getDuration(),
                'prep_time' => $recipe->getTime(),
            ],
            
            'ingredients' => array_map(fn($recipeIngredient) => [
                'id' => $recipeIngredient->getIngredient()->getId(),
                'name' => $recipeIngredient->getIngredient()->getName(),
                'quantity' => $recipeIngredient->getQuantity(),
                'unit' => $recipeIngredient->getUnit() ? [
                    'id' => $recipeIngredient->getUnit()->getId(),
                    'name' => $recipeIngredient->getUnit()->getName(),
                    'symbol' => $recipeIngredient->getUnit()->getSymbol(),
                ] : null,
            ], $recipe->getRecipeIngredients()->toArray()),
            
            'author' => $recipe->getUser()->getFirstname() . ' ' . $recipe->getUser()->getLastname(),
            'favorites_count' => $recipe->getUserRecipePreferences()?->count() ?? 0,
        ];
    }
}