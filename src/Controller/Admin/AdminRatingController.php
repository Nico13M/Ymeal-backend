<?php

namespace App\Controller\Admin;

use App\Entity\Rating;
use App\Entity\Recipe;
use App\Entity\User;
use App\Repository\RatingRepository;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/ratings', name: 'admin_rating_')]
class AdminRatingController extends AbstractController
{
    public function __construct(
        private UserManager $userManager,
        private EntityManagerInterface $em,
        private RatingRepository $ratingRepository
    ) {}

    /**
     * Retourne une réponse JSON avec le header X-User-Id
     */
    private function jsonWithUserId($data, int $statusCode = 200): Response
    {
        $response = $this->json($data, $statusCode);
        $user = $this->getUser();
        if ($user instanceof User) {
            $response->headers->set('X-User-Id', (string) $user->getId());
        }
        return $response;
    }

    // ============= HELPERS =============

    /**
     * Résout la recette depuis l'id ou retourne une 404
     */
    private function resolveRecipe(int $recipeId): Recipe|Response
    {
        $recipe = $this->em->getRepository(Recipe::class)->find($recipeId);

        if (!$recipe) {
            return $this->jsonWithUserId(['error' => 'Recette introuvable'], 404);
        }

        return $recipe;
    }

    /**
     * Sérialise un rating en tableau
     */
    private function serialize(Rating $rating, bool $withRecipe = false): array
    {
        $data = [
            'id'         => $rating->getId(),
            'rating'     => $rating->getRating(),
            'comment'    => $rating->getComment(),
            'created_at' => $rating->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $rating->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'user'       => [
                'id'   => $rating->getUser()->getId(),
                'name' => $rating->getUser()->getUserIdentifier(),
            ],
        ];

        if ($withRecipe) {
            $data['recipe'] = [
                'id'   => $rating->getRecipe()->getId(),
                'name' => $rating->getRecipe()->getName(),
            ];
        }

        return $data;
    }

    // ============= ROUTES =============

    /**
     * GET /admin/ratings/recipes/{recipeId}
     * Liste tous les ratings d'une recette avec la moyenne
     */
    #[Route('/recipes/{recipeId}', name: 'list_ratings', methods: ['GET'], requirements: ['recipeId' => '\d+'])]
    public function listRatings(Request $request, int $recipeId): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $recipe = $this->resolveRecipe($recipeId);
        if ($recipe instanceof Response) return $recipe;

        $ratings = $this->ratingRepository->findByRecipeWithUser($recipe);
        $stats   = $this->ratingRepository->getStatsForRecipe($recipe);

        return $this->jsonWithUserId([
            'stats'   => $stats,
            'ratings' => array_map(fn(Rating $r) => $this->serialize($r), $ratings),
        ]);
    }

    /**
     * POST /admin/ratings/create-or-update/{recipeId}
     * Créer ou mettre à jour le rating de l'utilisateur courant
     * Body: { rating: int (1-5), comment?: string }
     */
    #[Route('/create-or-update/{recipeId}', name: 'create_or_update_rating', methods: ['POST'], requirements: ['recipeId' => '\d+'])]
    public function createOrUpdateRating(Request $request, int $recipeId): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $recipe = $this->resolveRecipe($recipeId);
        if ($recipe instanceof Response) return $recipe;

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonWithUserId(['error' => 'Utilisateur non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        // Empêcher l'auteur de noter sa propre recette
        if ($recipe->getUser()->getId() === $user->getId()) {
            return $this->jsonWithUserId(['error' => 'Vous ne pouvez pas noter votre propre recette'], 403);
        }

        // Récupérer les données du JSON
       try {
            $data = $request->toArray();
        } catch (\Exception $e) {
            $content = $request->getContent();

            if (empty($content)) {
                return $this->jsonWithUserId([
                    'error' => 'Aucune donnée envoyée'
                ], 400);
            }

            $data = json_decode($content, true);

            if (!is_array($data)) {
                return $this->jsonWithUserId([
                    'error' => 'JSON invalide',
                    'received' => $content,
                    'decoded_type' => gettype($data),
                ], 400);
            }
        }

        // Validation
        if (!array_key_exists('rating', $data)) {
            return $this->jsonWithUserId(['error' => "Le champ 'rating' est obligatoire", 'received_data' => $data], 400);
        }

        $value = (int) $data['rating'];
        if ($value < 1 || $value > 5) {
            return $this->jsonWithUserId(['error' => 'La note doit être comprise entre 1 et 5'], 400);
        }

        // Upsert : mise à jour si déjà existant, création sinon
        $rating = $this->ratingRepository->findOneByUserAndRecipe($user, $recipe);
        $isNew  = $rating === null;

        if ($isNew) {
            $rating = new Rating();
            $rating->setUser($user);
            $rating->setRecipe($recipe);
        }

        $rating->setRating($value);
        $rating->setComment($data['comment'] ?? null);

        if ($isNew) {
            $this->em->persist($rating);
        }

        $this->em->flush();

        return $this->jsonWithUserId([
            'success' => true,
            'message' => $isNew ? 'Note ajoutée avec succès' : 'Note mise à jour avec succès',
            'rating'  => $this->serialize($rating),
            'stats'   => $this->ratingRepository->getStatsForRecipe($recipe),
        ], $isNew ? 201 : 200);
    }

    /**
     * DELETE /admin/ratings/delete/{recipeId}
     * Supprimer le rating de l'utilisateur courant
     */
    #[Route('/delete/{recipeId}', name: 'delete_rating', methods: ['DELETE'], requirements: ['recipeId' => '\d+'])]
    public function deleteRating(Request $request, int $recipeId): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $recipe = $this->resolveRecipe($recipeId);
        if ($recipe instanceof Response) return $recipe;

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonWithUserId(['error' => 'Utilisateur non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $rating = $this->ratingRepository->findOneByUserAndRecipe($user, $recipe);

        if (!$rating) {
            return $this->jsonWithUserId(['error' => "Vous n'avez pas encore noté cette recette"], 404);
        }

        $this->em->remove($rating);
        $this->em->flush();

        return $this->jsonWithUserId([
            'success' => true,
            'message' => 'Note supprimée avec succès',
            'stats'   => $this->ratingRepository->getStatsForRecipe($recipe),
        ]);
    }

    /**
     * GET /admin/ratings/recipes/{recipeId}/me
     * Récupérer le rating de l'utilisateur courant pour cette recette
     */
    #[Route('/recipes/{recipeId}/me', name: 'get_my_rating', methods: ['GET'], requirements: ['recipeId' => '\d+'])]
    public function getMyRating(Request $request, int $recipeId): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $recipe = $this->resolveRecipe($recipeId);
        if ($recipe instanceof Response) return $recipe;

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonWithUserId(['error' => 'Utilisateur non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $rating = $this->ratingRepository->findOneByUserAndRecipe($user, $recipe);

        if (!$rating) {
            return $this->jsonWithUserId(['rating' => null]);
        }

        return $this->jsonWithUserId(['rating' => $this->serialize($rating)]);
    }

    /**
     * POST /admin/ratings/debug
     * Debug pour voir ce qui est reçu
     */
    #[Route('/debug', name: 'debug', methods: ['POST'])]
    public function debug(Request $request): Response
    {
        return $this->json([
            'content_type' => $request->headers->get('Content-Type'),
            'content' => $request->getContent(),
            'toArray_result' => $request->toArray(),
            'json_decode_result' => json_decode($request->getContent(), true),
        ], 200);
    }
    }