<?php
namespace App\Controller\Admin;

use App\Entity\Diet;
use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\Units;
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
        private UserManager $userManager, // Gestion de l'authentification
        private RecipeService $recipeService, // Sérialisation des recettes
        private UserDataService $userDataService, // Données utilisateur
        private DataService $dataService, // Envoi de données
        private RecipeSearchService $recipeSearchService, // Recherche avancée
        private EntityManagerInterface $em // ORM Doctrine
    ) {}

    // ============= ROUTES SPÉCIFIQUES (AVANT {id}) =============
    // IMPORTANT: Les routes spécifiques DOIVENT être avant /{id} sinon /create sera matchée par /{id}!

    /**
     * GET /admin/recipes
     * Liste toutes les recettes publiques
     */
    #[Route('/index', name: 'index', methods: ['GET'])]
    public function index(Request $request, RecipeRepository $recipeRepository): Response
    {
        // Vérifier l'authentification
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);
        $recipes = $recipeRepository->findAll();
        $data = array_map(fn(Recipe $r) => $this->recipeService->serializeRecipe($r, $user), $recipes);

        return $this->json($data);
    }

    /**
     * GET /admin/recipes/search?difficulty=easy&dish_type=pasta
     * Recherche avancée avec filtres multiples
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);

        // Appeler le service de recherche
        $result = $this->recipeSearchService->searchRecipes($user, $request);

        return $this->json($result['data'], 200);
    }

    /**
     * POST /admin/recipes/create
     * Créer une nouvelle recette
     * Body: { name, description, servings, is_public, difficulty?, time?, duration?, dish_type?, ingredients?, diet_ids? }
     */
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

        // VALIDATION des champs obligatoires
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
            // Créer la recette
            $recipe = new Recipe();
            $recipe->setName($data['name']);
            $recipe->setDescription($data['description']);
            $recipe->setServings((int) $data['servings']);
            $recipe->setIsPublic((bool) $data['is_public']);
            $recipe->setUser($user); // Lier à l'auteur

            // Champs optionnels
            if (isset($data['duration'])) $recipe->setDuration((int) $data['duration']);
            if (isset($data['time'])) $recipe->setTime((int) $data['time']);
            if (isset($data['difficulty'])) $recipe->setDifficulty($data['difficulty']);
            if (isset($data['dish_type'])) $recipe->setDishType($data['dish_type']);
            if (isset($data['image'])) $recipe->setImage($data['image']);
            if (isset($data['steps']) && is_array($data['steps'])) $recipe->setSteps($data['steps']);

            // Ajouter les régimes alimentaires
            if (isset($data['diet_ids']) && is_array($data['diet_ids'])) {
                $diets = $this->em->getRepository(Diet::class)->findBy(['id' => $data['diet_ids']]);
                foreach ($diets as $diet) {
                    $recipe->addDietsHasRecipe($diet);
                }
            }

            // Ajouter les ingrédients
            if (isset($data['ingredients']) && is_array($data['ingredients'])) {
                foreach ($data['ingredients'] as $ingData) {
                    $ingredient = $this->em->getRepository(Ingredient::class)
                        ->find($ingData['ingredient_id'] ?? null);

                    if (!$ingredient) continue;

                    // Créer la relation RecipeIngredient
                    $recipeIngredient = new \App\Entity\RecipeIngredient();
                    $recipeIngredient->setRecipe($recipe);
                    $recipeIngredient->setIngredient($ingredient);
                    $recipeIngredient->setQuantity((float) ($ingData['quantity'] ?? 0));
                    
                    // Récupérer l'unité si fournie
                    if (isset($ingData['unit_id'])) {
                        $unit = $this->em->getRepository(Units::class)->find($ingData['unit_id']);
                        $recipeIngredient->setUnit($unit);
                    }

                    $recipe->addRecipeIngredient($recipeIngredient);
                    $this->em->persist($recipeIngredient);
                }
            }

            // 💾 Sauvegarder la recette
            $this->em->persist($recipe);
            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Recette créée avec succès',
                'recipe' => $this->recipeService->serializeRecipe($recipe, $user)
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /admin/recipes/user/blacklist
     * Récupérer la liste noire des ingrédients de l'utilisateur
     */
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

    /**
     * GET /admin/recipes/favorites
     * Récupérer les recettes favorites de l'utilisateur
     */
    #[Route('/favorites', name: 'favorites', methods: ['GET'])]
    public function getFavorites(Request $request): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);
        $favorites = $user->getUserRecipePreferences();

        $data = array_map(fn(Recipe $r) => $this->recipeService->serializeRecipe($r, $user), $favorites->toArray());

        return $this->json($data);
    }

    /**
     * POST /admin/recipes/user/data/send
     * Envoyer les données de l'utilisateur (RGPD)
     */
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
    // Ces routes DOIVENT être après les routes spécifiques!

    /**
     * GET /admin/recipes/{id}
     * Obtenir les détails complets d'une recette
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, Recipe $recipe): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);

        return $this->json($this->recipeService->serializeRecipe($recipe, $user), 200);
    }

    /**
     * PATCH /admin/recipes/{id}/edit
     * Modifier une recette existante
     * Body: { name?, description?, servings?, difficulty?, time?, duration?, dish_type?, image?, is_public?, diet_ids?, ingredients? }
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Recipe $recipe, EntityManagerInterface $em): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        // Vérifier que l'utilisateur est l'auteur
        if ($recipe->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Non autorisé'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Mettre à jour les champs fournis
        if (isset($data['name'])) $recipe->setName($data['name']);
        if (isset($data['description'])) $recipe->setDescription($data['description']);
        if (isset($data['servings'])) $recipe->setServings((int) $data['servings']);
        if (isset($data['duration'])) $recipe->setDuration((int) $data['duration']);
        if (isset($data['time'])) $recipe->setTime((int) $data['time']);
        if (isset($data['difficulty'])) $recipe->setDifficulty($data['difficulty']);
        if (isset($data['dish_type'])) $recipe->setDishType($data['dish_type']);
        if (isset($data['image'])) $recipe->setImage($data['image']);
        if (isset($data['is_public'])) $recipe->setIsPublic((bool) $data['is_public']);
        if (isset($data['steps']) && is_array($data['steps'])) $recipe->setSteps($data['steps']);

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
            // Supprimer les anciens ingrédients
            foreach ($recipe->getRecipeIngredients() as $recipeIng) {
                $recipe->removeRecipeIngredient($recipeIng);
                $em->remove($recipeIng);
            }

            // Ajouter les nouveaux
            foreach ($data['ingredients'] as $ingData) {
                $ingredient = $em->getRepository(Ingredient::class)
                    ->find($ingData['ingredient_id'] ?? null);

                if (!$ingredient) continue;

                $recipeIngredient = new \App\Entity\RecipeIngredient();
                $recipeIngredient->setRecipe($recipe);
                $recipeIngredient->setIngredient($ingredient);
                $recipeIngredient->setQuantity((float) ($ingData['quantity'] ?? 0));
                
                // Récupérer l'unité si fournie
                if (isset($ingData['unit_id'])) {
                    $unit = $em->getRepository(Units::class)->find($ingData['unit_id']);
                    $recipeIngredient->setUnit($unit);
                }

                $recipe->addRecipeIngredient($recipeIngredient);
                $em->persist($recipeIngredient);
            }
        }

        $em->flush();

        return $this->json($this->recipeService->serializeRecipe($recipe, $user), 200);
    }

    /**
     * DELETE /admin/recipes/{id}/delete
     * Supprimer une recette
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Recipe $recipe, EntityManagerInterface $em): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        // Vérifier que l'utilisateur est l'auteur
        if ($recipe->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Non autorisé'], 403);
        }

        $em->remove($recipe);
        $em->flush();

        return $this->json(['message' => 'Recette supprimée avec succès'], 200);
    }

    /**
     * GET /admin/recipes/{id}/favorite/status
     * Vérifier si une recette est en favoris pour l'utilisateur courant
     */
    #[Route('/{id}/favorite/status', name: 'favorite_status', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getFavoriteStatus(Request $request, Recipe $recipe): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);

        return $this->json([
            'is_favorited' => $user->getUserRecipePreferences()->contains($recipe),
            'favorites_count' => $recipe->getUserRecipePreferences()->count()
        ], 200);
    }

    /**
     * POST /admin/recipes/{id}/favorite
     * Ajouter une recette aux favoris
     */
    #[Route('/{id}/favorite', name: 'add_favorite', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addToFavorites(Request $request, Recipe $recipe, EntityManagerInterface $em): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);

        // Vérifier que la recette n'est pas déjà en favoris
        if ($user->getUserRecipePreferences()->contains($recipe)) {
            return $this->json(['message' => 'La recette est déjà dans vos favoris'], 400);
        }

        $user->addUserRecipePreference($recipe);
        $em->flush();

        return $this->json([
            'message' => 'Recette ajoutée aux favoris',
            'is_favorited' => true,
            'favorites_count' => $recipe->getUserRecipePreferences()->count()
        ], 200);
    }

    /**
     * DELETE /admin/recipes/{id}/favorite
     * Retirer une recette des favoris
     */
    #[Route('/{id}/favorite', name: 'remove_favorite', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function removeFromFavorites(Request $request, Recipe $recipe, EntityManagerInterface $em): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        assert($user instanceof User);

        // Vérifier que la recette est bien en favoris
        if (!$user->getUserRecipePreferences()->contains($recipe)) {
            return $this->json(['message' => 'La recette n\'est pas dans vos favoris'], 400);
        }

        $user->removeUserRecipePreference($recipe);
        $em->flush();

        return $this->json([
            'message' => 'Recette supprimée des favoris',
            'is_favorited' => false,
            'favorites_count' => $recipe->getUserRecipePreferences()->count()
        ], 200);
    }

    #[Route('/count', name: 'count', methods: ['GET'])]
    public function count(Request $request, RecipeRepository $recipeRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $criteria = [];

        if ($request->query->has('is_public')) {
            $criteria['isPublic'] = filter_var($request->query->get('is_public'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->query->has('difficulty')) {
            $criteria['difficulty'] = $request->query->get('difficulty');
        }

        if ($request->query->has('dish_type')) {
            $criteria['dishType'] = $request->query->get('dish_type');
        }

        // Compter uniquement les recettes de l'utilisateur courant
        if ($request->query->getBoolean('mine')) {
            $user = $this->getUser();
            assert($user instanceof User);
            $criteria['user'] = $user;
        }

        $count = empty($criteria)
            ? count($recipeRepository->findAll())
            : $recipeRepository->count($criteria);

        return $this->json([
            'count' => $count,
            'filters' => $criteria ? array_keys($criteria) : []
        ]);
    }
}