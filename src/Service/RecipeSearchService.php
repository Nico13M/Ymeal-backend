<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\RecipeRepository;
use App\Repository\IngredientRepository;
use Symfony\Component\HttpFoundation\Request;

class RecipeSearchService
{
    public function __construct(
        private RecipeRepository $recipeRepository,
        private IngredientRepository $ingredientRepository,
        private RecipeService $recipeService
    ) {}

    /**
     * Effectue une recherche de recettes basée sur les critères utilisateur
     */
    public function searchRecipes(User $user, Request $request): array
    {
        $blacklistIds = $this->getUserBlacklistIds($user);
        $dietIds = $this->getUserDietIds($user);
        $ingredientsData = $this->processIngredients($user, $request);
        $frigoIngredients = $ingredientsData['frigo'];
        $formIngredients = $ingredientsData['form'];
        $allIngredients = array_merge($frigoIngredients, $formIngredients);
        $servings = (int) $request->query->get('servings', 4);

        // Construire la requête avec les filtres
        $qb = $this->recipeRepository->createQueryBuilder('r');

        if ($dietIds) {
            $qb->leftJoin('r.dietsHasRecipe', 'd')
               ->andWhere('d.id IN (:dietIds)')
               ->setParameter('dietIds', $dietIds);
        }

        if ($blacklistIds) {
            $qb->leftJoin('r.recipeIngredients', 'ri')
               ->leftJoin('ri.ingredient', 'i')
               ->andWhere('i.id NOT IN (:blacklistIds)')
               ->setParameter('blacklistIds', $blacklistIds);
        }

        $recipes = $qb->andWhere('r.is_public = true')
                     ->getQuery()
                     ->getResult();

        // Sérialiser les recettes
        $serializedRecipes = array_map(
            fn($recipe) => $this->recipeService->serializeRecipeMinimal($recipe),  // Minimaliste
            $recipes
        );

        return [
            'success' => true,
            'data' => [
                'recipes' => $serializedRecipes,
                'total_results' => count($recipes),
                'applied_filters' => $this->buildFiltersInfo(
                    $user,
                    $blacklistIds,
                    $dietIds,
                    $frigoIngredients,
                    $formIngredients,
                    $servings
                )
            ]
        ];
    }

    /**
     * Construit les infos de filtres appliqués de manière lisible
     */
    private function buildFiltersInfo(
        User $user,
        array $blacklistIds,
        array $dietIds,
        array $frigoIngredients,
        array $formIngredients,
        int $servings
    ): array
    {
        return [
            'diets' => [
                'count' => count($dietIds),
                'details' => $user->getDiets()
                    ->filter(fn($diet) => in_array($diet->getId(), $dietIds))
                    ->map(fn($diet) => [
                        'id' => $diet->getId(),
                        'name' => $diet->getName(),
                    ])
                    ->toArray(),
            ],
            'blacklist' => [
                'count' => count($blacklistIds),
                'message' => count($blacklistIds) > 0 
                    ? "Ingrédients à exclure: " . count($blacklistIds)
                    : "Aucune restriction"
            ],
            'ingredients' => [
                'frigo' => [
                    'count' => count($frigoIngredients),
                    'items' => $frigoIngredients,
                ],
                'manual' => [
                    'count' => count($formIngredients),
                    'items' => $formIngredients,
                ],
                'total' => count($frigoIngredients) + count($formIngredients),
            ],
            'servings' => $servings,
        ];
    }

    private function getUserBlacklistIds(User $user): array
    {
        return $user->getUserIngredientsBlacklist()
            ->map(fn($ingredient) => $ingredient->getId())
            ->toArray();
    }

    private function getUserDietIds(User $user): array
    {
        return $user->getDiets()
            ->map(fn($diet) => $diet->getId())
            ->toArray();
    }

    private function processIngredients(User $user, Request $request): array
    {
        $frigo = $request->query->get('frigo', false);
        $frigoIngredients = [];
        $formIngredients = [];

        if ($frigo && $user->getFrigo()) {
            $frigoIngredients = $user->getFrigo()->getIngredientsHasFrigo()
                ->map(fn($ingredient) => [
                    'id' => $ingredient->getId(),
                    'name' => $ingredient->getName()
                ])
                ->toArray();
        }

        $ingredientsFormParam = $request->query->get('ingredientsForm', '');
        if (!empty($ingredientsFormParam)) {
            if (str_starts_with($ingredientsFormParam, '[')) {
                $formIngredients = json_decode($ingredientsFormParam, true) ?? [];
            } else {
                $ids = array_map('intval', explode(',', $ingredientsFormParam));
                $ingredients = $this->ingredientRepository->findByIds($ids);
                $formIngredients = array_map(fn($ingredient) => [
                    'id' => $ingredient->getId(),
                    'name' => $ingredient->getName()
                ], $ingredients);
            }
        }

        return [
            'frigo' => $frigoIngredients,
            'form' => $formIngredients
        ];
    }
}