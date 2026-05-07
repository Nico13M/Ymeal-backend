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

#[Route('/admin/recipes/{recipeId}/ratings', name: 'admin_rating_', requirements: ['recipeId' => '\d+'])]
class AdminRatingController extends AbstractController
{
    public function __construct(
        private UserManager $userManager,
        private EntityManagerInterface $em,
        private RatingRepository $ratingRepository
    ) {}

    // ============= HELPERS =============

    /**
     * Résout la recette depuis l'id ou retourne une 404
     */
    private function resolveRecipe(int $recipeId): Recipe|Response
    {
        $recipe = $this->em->getRepository(Recipe::class)->find($recipeId);

        if (!$recipe) {
            return $this->json(['error' => 'Recette introuvable'], 404);
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
     * GET /admin/recipes/{recipeId}/ratings
     * Liste tous les ratings d'une recette avec la moyenne
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, int $recipeId): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $recipe = $this->resolveRecipe($recipeId);
        if ($recipe instanceof Response) return $recipe;

        $ratings = $this->ratingRepository->findByRecipeWithUser($recipe);
        $stats   = $this->ratingRepository->getStatsForRecipe($recipe);

        return $this->json([
            'stats'   => $stats,
            'ratings' => array_map(fn(Rating $r) => $this->serialize($r), $ratings),
        ]);
    }

    /**
     * POST /admin/recipes/{recipeId}/ratings
     * Créer ou mettre à jour le rating de l'utilisateur courant
     * Body: { rating: int (1-5), comment?: string }
     */
    #[Route('', name: 'upsert', methods: ['POST'])]
    public function upsert(Request $request, int $recipeId): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $recipe = $this->resolveRecipe($recipeId);
        if ($recipe instanceof Response) return $recipe;

        $user = $this->getUser();
        assert($user instanceof User);

        // Empêcher l'auteur de noter sa propre recette
        if ($recipe->getUser()->getId() === $user->getId()) {
            return $this->json(['error' => 'Vous ne pouvez pas noter votre propre recette'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Validation
        if (!isset($data['rating'])) {
            return $this->json(['error' => "Le champ 'rating' est obligatoire"], 400);
        }

        $value = (int) $data['rating'];
        if ($value < 1 || $value > 5) {
            return $this->json(['error' => 'La note doit être comprise entre 1 et 5'], 400);
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

        return $this->json([
            'success' => true,
            'message' => $isNew ? 'Note ajoutée avec succès' : 'Note mise à jour avec succès',
            'rating'  => $this->serialize($rating),
            'stats'   => $this->ratingRepository->getStatsForRecipe($recipe),
        ], $isNew ? 201 : 200);
    }

    /**
     * DELETE /admin/recipes/{recipeId}/ratings
     * Supprimer le rating de l'utilisateur courant
     */
    #[Route('', name: 'delete', methods: ['DELETE'])]
    public function delete(Request $request, int $recipeId): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $recipe = $this->resolveRecipe($recipeId);
        if ($recipe instanceof Response) return $recipe;

        $user = $this->getUser();
        assert($user instanceof User);

        $rating = $this->ratingRepository->findOneByUserAndRecipe($user, $recipe);

        if (!$rating) {
            return $this->json(['error' => "Vous n'avez pas encore noté cette recette"], 404);
        }

        $this->em->remove($rating);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Note supprimée avec succès',
            'stats'   => $this->ratingRepository->getStatsForRecipe($recipe),
        ]);
    }

    /**
     * GET /admin/recipes/{recipeId}/ratings/me
     * Récupérer le rating de l'utilisateur courant pour cette recette
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(Request $request, int $recipeId): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $recipe = $this->resolveRecipe($recipeId);
        if ($recipe instanceof Response) return $recipe;

        $user = $this->getUser();
        assert($user instanceof User);

        $rating = $this->ratingRepository->findOneByUserAndRecipe($user, $recipe);

        if (!$rating) {
            return $this->json(['rating' => null]);
        }

        return $this->json(['rating' => $this->serialize($rating)]);
    }
}