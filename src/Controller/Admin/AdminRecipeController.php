<?php

namespace App\Controller\Admin;

use App\Entity\Diet;
use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use App\Service\UserDataService;
use App\Service\DataService;
use App\Service\UserManager;
use App\Service\RecipeService;
use App\Service\RecipeSearchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;

#[Route('/admin/recipes', name: 'admin_recipe_')]
class AdminRecipeController extends AbstractController
{
    public function __construct(
        private UserManager $userManager,
        private RecipeService $recipeService,
        private UserDataService $userDataService,
        private DataService $dataService,
        private RecipeSearchService $recipeSearchService,
        private EntityManagerInterface $em
    ) {}

    // ============= ROUTES SPÉCIFIQUES (AVANT {id}) =============

    // LISTE
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, RecipeRepository $recipeRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $recipes = $recipeRepository->findAll();
        $data = array_map(fn(Recipe $r) => $this->recipeService->serializeRecipe($r), $recipes);

        return $this->json($data);
    }

    // RECHERCHE
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);

        $result = $this->recipeSearchService->searchRecipes($user, $request);

        return $this->json($result['data'], 200);
    }

    // ⭐ CRÉATION (AVANT {id}!)
    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        // ✅ VALIDATION
        $required = ['name', 'description', 'servings', 'is_public'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->json([
                    'success' => false,
                    'error' => "Le champ '$field' est obligatoire"
                ], 400);
            }
        }

        try {
            // 🍽️ Créer la recette
            $recipe = new Recipe();
            $recipe->setName($data['name']);
            $recipe->setDescription($data['description']);
            $recipe->setServings((int) $data['servings']);
            $recipe->setIsPublic((bool) $data['is_public']);
            $recipe->setUser($user);

            // 🔧 Optionnels
            if (isset($data['duration'])) $recipe->setDuration((int) $data['duration']);
            if (isset($data['time'])) $recipe->setTime((int) $data['time']);
            if (isset($data['difficulty'])) $recipe->setDifficulty($data['difficulty']);
            if (isset($data['dish_type'])) $recipe->setDishType($data['dish_type']);
            if (isset($data['image'])) $recipe->setImage($data['image']);

            // 🥗 Ajouter les régimes
            if (isset($data['diet_ids']) && is_array($data['diet_ids'])) {
                $diets = $this->em->getRepository(Diet::class)->findBy(['id' => $data['diet_ids']]);
                foreach ($diets as $diet) {
                    $recipe->addDietsHasRecipe($diet);
                }
            }

            // 🥘 Ajouter les ingrédients
            if (isset($data['ingredients']) && is_array($data['ingredients'])) {
                foreach ($data['ingredients'] as $ingData) {
                    $ingredient = $this->em->getRepository(Ingredient::class)
                        ->find($ingData['ingredient_id'] ?? null);

                    if (!$ingredient) continue;

                    $recipeIngredient = new \App\Entity\RecipeIngredient();
                    $recipeIngredient->setRecipe($recipe);
                    $recipeIngredient->setIngredient($ingredient);
                    $recipeIngredient->setQuantity((float) ($ingData['quantity'] ?? 0));
                    $recipeIngredient->setUnit($ingData['unit'] ?? null);

                    $recipe->addRecipeIngredient($recipeIngredient);
                    $this->em->persist($recipeIngredient);
                }
            }

            // 💾 Sauvegarder
            $this->em->persist($recipe);
            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Recette créée avec succès',
                'recipe' => $this->recipeService->serializeRecipe($recipe)
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // BLACKLIST
    #[Route('/user/blacklist', name: 'user_blacklist', methods: ['GET'])]
    public function getUserBlacklist(Request $request): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);
        $blacklist = $user->getUserIngredientsBlacklist();

        $data = array_map(fn($ingredient) => [
            'id' => $ingredient->getId(),
            'name' => $ingredient->getName(),
            'slug' => $ingredient->getSlug()
        ], $blacklist->toArray());

        return $this->json($data);
    }

    // FAVORIS
    #[Route('/favorites', name: 'favorites', methods: ['GET'])]
    public function getFavorites(Request $request): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);
        $favorites = $user->getUserRecipePreferences();

        $data = array_map(fn(Recipe $r) => $this->recipeService->serializeRecipe($r), $favorites->toArray());

        return $this->json($data);
    }

    // ENVOYER DONNÉES UTILISATEUR
    #[Route('/user/data/send', name: 'send_user_data', methods: ['POST'])]
    public function sendUserData(Request $request): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);

        $userData = $this->userDataService->getUserData($user);
        $result = $this->dataService->sendUserData($userData, $user->getId());

        if ($result['success']) {
            return $this->json([
                'message' => 'Données utilisateur envoyées avec succès',
                'status_code' => $result['status_code'],
                'response' => $result['data'] ?? null
            ], 200);
        } else {
            return $this->json([
                'error' => 'Erreur lors de l\'envoi des données',
                'details' => $result['error'] ?? 'Erreur inconnue'
            ], 500);
        }
    }

    // ============= ROUTES GÉNÉRIQUES (APRÈS {id}) =============

    // ============= DÉTAILS & MODIFICATION =============

    // DÉTAIL COMPLET
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, Recipe $recipe): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        return $this->json($this->recipeService->serializeRecipe($recipe), 200);
    }

    // MODIFICATION
    #[Route('/{id}/edit', name: 'edit', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Recipe $recipe, EntityManagerInterface $em): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        if ($recipe->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Non autorisé'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['name'])) $recipe->setName($data['name']);
        if (isset($data['description'])) $recipe->setDescription($data['description']);
        if (isset($data['servings'])) $recipe->setServings((int) $data['servings']);
        if (isset($data['duration'])) $recipe->setDuration((int) $data['duration']);
        if (isset($data['time'])) $recipe->setTime((int) $data['time']);
        if (isset($data['difficulty'])) $recipe->setDifficulty($data['difficulty']);
        if (isset($data['dish_type'])) $recipe->setDishType($data['dish_type']);
        if (isset($data['image'])) $recipe->setImage($data['image']);
        if (isset($data['is_public'])) $recipe->setIsPublic((bool) $data['is_public']);

        // Modifier les régimes
        if (isset($data['diet_ids'])) {
            foreach ($recipe->getDietsHasRecipe() as $diet) {
                $recipe->removeDietsHasRecipe($diet);
            }
            $diets = $em->getRepository(Diet::class)->findBy(['id' => $data['diet_ids']]);
            foreach ($diets as $diet) {
                $recipe->addDietsHasRecipe($diet);
            }
        }

        // Modifier les ingrédients
        if (isset($data['ingredients'])) {
            foreach ($recipe->getRecipeIngredients() as $recipeIng) {
                $recipe->removeRecipeIngredient($recipeIng);
                $em->remove($recipeIng);
            }

            foreach ($data['ingredients'] as $ingData) {
                $ingredient = $em->getRepository(Ingredient::class)
                    ->find($ingData['ingredient_id'] ?? null);

                if (!$ingredient) continue;

                $recipeIngredient = new \App\Entity\RecipeIngredient();
                $recipeIngredient->setRecipe($recipe);
                $recipeIngredient->setIngredient($ingredient);
                $recipeIngredient->setQuantity((float) ($ingData['quantity'] ?? 0));
                $recipeIngredient->setUnit($ingData['unit'] ?? null);

                $recipe->addRecipeIngredient($recipeIngredient);
                $em->persist($recipeIngredient);
            }
        }

        $em->flush();

        return $this->json($this->recipeService->serializeRecipe($recipe), 200);
    }

    // ============= SUPPRESSION & FAVORIS =============

    // SUPPRESSION
    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Recipe $recipe, EntityManagerInterface $em): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        if ($recipe->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Non autorisé'], 403);
        }

        $em->remove($recipe);
        $em->flush();

        return $this->json(['message' => 'Recette supprimée avec succès'], 200);
    }

    // AJOUTER AUX FAVORIS
    #[Route('/{id}/favorite', name: 'add_favorite', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addToFavorites(Request $request, Recipe $recipe, EntityManagerInterface $em): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);

        if ($user->getUserRecipePreferences()->contains($recipe)) {
            return $this->json(['message' => 'La recette est déjà dans vos favoris'], 400);
        }

        $user->addUserRecipePreference($recipe);
        $em->flush();

        return $this->json([
            'message' => 'Recette ajoutée aux favoris',
            'favorites_count' => $recipe->getUserRecipePreferences()->count()
        ], 200);
    }

    // RETIRER DES FAVORIS
    #[Route('/{id}/favorite', name: 'remove_favorite', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function removeFromFavorites(Request $request, Recipe $recipe, EntityManagerInterface $em): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);

        if (!$user->getUserRecipePreferences()->contains($recipe)) {
            return $this->json(['message' => 'La recette n\'est pas dans vos favoris'], 400);
        }

        $user->removeUserRecipePreference($recipe);
        $em->flush();

        return $this->json([
            'message' => 'Recette supprimée des favoris',
            'favorites_count' => $recipe->getUserRecipePreferences()->count()
        ], 200);
    }
}