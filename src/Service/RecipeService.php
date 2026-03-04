<?php

namespace App\Service;

use App\Entity\Recipe;

class RecipeService
{
    /**
     * 🍽️ Sérialise une recette avec tous ses détails
     */
    public function serializeRecipe(Recipe $recipe): array
    {
        return [
            'id' => $recipe->getId(),
            'name' => $recipe->getName(),
            'slug' => $recipe->getSlug(),
            'description' => $recipe->getDescription(),
            'image' => $recipe->getImage(),
            'servings' => $recipe->getServings(),
            'timing' => [
                'duration' => $recipe->getDuration(),
                'prep_time' => $recipe->getTime(),
                'total_time' => ($recipe->getDuration() ?? 0) + ($recipe->getTime() ?? 0),
            ],
            'difficulty' => $recipe->getDifficulty(),
            'dish_type' => $recipe->getDishType(),
            'is_public' => $recipe->getIsPublic(),
            'timestamps' => [
                'created_at' => $recipe->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $recipe->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ],
            'author' => $recipe->getUser() ? [
                'id' => $recipe->getUser()->getId(),
                'name' => $recipe->getUser()->getFirstname() . ' ' . $recipe->getUser()->getLastname(),
                'email' => $recipe->getUser()->getEmail(),
            ] : null,
            'nutrition' => [
                'diets' => array_map(fn($diet) => [
                    'id' => $diet->getId(),
                    'name' => $diet->getName(),
                ], $recipe->getDietsHasRecipe()->toArray()),
                'ingredients' => array_map(fn($recipeIngredient) => [
                    'id' => $recipeIngredient->getIngredient()->getId(),
                    'name' => $recipeIngredient->getIngredient()->getName(),
                    'quantity' => $recipeIngredient->getQuantity(),
                    'unit' => $recipeIngredient->getUnit(),
                ], $recipe->getRecipeIngredients()->toArray()),
            ],
            'engagement' => [
                'favorites_count' => $recipe->getUserRecipePreferences()?->count() ?? 0,
            ],
        ];
    }

    /**
     * 📋 Sérialise une recette en version minimaliste (pour les listes)
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
            'timing' => [
                'duration' => $recipe->getDuration(),
                'prep_time' => $recipe->getTime(),
            ],
            'author' => $recipe->getUser()->getFirstname() . ' ' . $recipe->getUser()->getLastname(),
            'favorites_count' => $recipe->getUserRecipePreferences()?->count() ?? 0,
        ];
    }
}