<?php

namespace App\Controller\Admin;

use App\Entity\Recipe;
use App\Entity\User;
use App\Service\AiRecipeParserService;
use App\Service\RecipeService;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/recipes/ai', name: 'admin_ai_recipe_')]
class AdminAiRecipeController extends AbstractController
{
    public function __construct(
        private UserManager $userManager,
        private AiRecipeParserService $parser,
        private RecipeService $recipeService,
        private EntityManagerInterface $em
    ) {}

    /**
     * POST /admin/recipes/ai/save
     * Sauvegarde une recette générée par l'IA dans la base de données.
     * Body: { recipe_text: string, is_public?: bool, dish_type?: string }
     */
    #[Route('/save', name: 'save', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $recipeText = trim($data['recipe_text'] ?? '');
        if ($recipeText === '') {
            return $this->json(
                ['success' => false, 'error' => 'Le champ recipe_text est obligatoire'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $parsed = $this->parser->parse($recipeText);

            $recipe = new Recipe();
            $recipe->setName($parsed['name']);
            $recipe->setDescription($parsed['description']);
            $recipe->setServings($parsed['servings']);
            $recipe->setIsPublic((bool) ($data['is_public'] ?? false));
            $recipe->setUser($user);

            if ($parsed['duration'] !== null) {
                $recipe->setDuration($parsed['duration']);
            }

            if ($parsed['difficulty'] !== null) {
                $recipe->setDifficulty($parsed['difficulty']);
            }

            if (!empty($parsed['steps'])) {
                $recipe->setSteps($parsed['steps']);
            }

            $dishType = trim($data['dish_type'] ?? '');
            if ($dishType !== '') {
                $recipe->setDishType($dishType);
            }

            $this->em->persist($recipe);
            $this->em->flush();

            return $this->json(
                [
                    'success' => true,
                    'message' => 'Recette sauvegardée avec succès',
                    'recipe'  => $this->recipeService->serializeRecipe($recipe, $user),
                ],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->json(
                ['success' => false, 'error' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
