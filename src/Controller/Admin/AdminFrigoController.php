<?php
namespace App\Controller\Admin;

use App\Entity\Frigo;
use App\Entity\Ingredient;
use App\Entity\User;
use App\Repository\IngredientRepository;
use App\Repository\UserRepository;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/frigo', name: 'admin_frigo_')]
class AdminFrigoController extends AbstractController
{
    public function __construct(private UserManager $userManager)
    {
    }

    private function resolveUser(Request $request, UserRepository $userRepository): ?User
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            return $user;
        }

        $userId = $request->headers->get('X-User-Id');
        if ($userId && is_numeric($userId)) {
            return $userRepository->find((int) $userId);
        }

        return null;
    }

    private function getOrCreateFrigo(User $user, EntityManagerInterface $em): Frigo
    {
        $frigo = $user->getFrigo();

        if (!$frigo) {
            $frigo = new Frigo();
            $frigo->setUserFrigo($user);
            $em->persist($frigo);
            $em->flush();
        }

        return $frigo;
    }

    // --- 0. LISTER TOUS LES INGRÉDIENTS DISPONIBLES ---
    #[Route('/ingredients', name: 'list_ingredients', methods: ['GET'])]
    public function listIngredients(Request $request, IngredientRepository $ingredientRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $ingredients = $ingredientRepository->findAll();

        $data = array_map(fn(Ingredient $i) => [
            'id'   => $i->getId(),
            'name' => $i->getName(),
            'slug' => $i->getSlug(),
        ], $ingredients);

        return $this->json($data);
    }

    // --- 1. LISTER LES INGRÉDIENTS DU FRIGO ---
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->resolveUser($request, $userRepository);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $frigo = $this->getOrCreateFrigo($user, $em);

        $data = array_map(fn(Ingredient $i) => [
            'id'   => $i->getId(),
            'name' => $i->getName(),
            'slug' => $i->getSlug(),
        ], $frigo->getIngredientsHasFrigo()->toArray());

        return $this->json($data);
    }

    // --- 2. AJOUTER UN INGRÉDIENT AU FRIGO ---
    #[Route('/{id}', name: 'add_ingredient', methods: ['POST'])]
    public function addIngredient(
        Request $request,
        Ingredient $ingredient,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->resolveUser($request, $userRepository);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $frigo = $this->getOrCreateFrigo($user, $em);

        if ($frigo->getIngredientsHasFrigo()->contains($ingredient)) {
            return $this->json(['message' => 'Ingrédient déjà dans le frigo'], 400);
        }

        $frigo->addIngredientsHasFrigo($ingredient);
        $em->flush();

        return $this->json(['message' => 'Ingrédient ajouté au frigo'], 200);
    }

    // --- 3. SUPPRIMER UN INGRÉDIENT DU FRIGO ---
    #[Route('/{id}', name: 'remove_ingredient', methods: ['DELETE'])]
    public function removeIngredient(
        Request $request,
        Ingredient $ingredient,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->resolveUser($request, $userRepository);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $frigo = $user->getFrigo();
        if (!$frigo) {
            return $this->json(['error' => 'Frigo non trouvé'], 404);
        }

        if (!$frigo->getIngredientsHasFrigo()->contains($ingredient)) {
            return $this->json(['message' => 'Ingrédient non trouvé dans le frigo'], 400);
        }

        $frigo->removeIngredientsHasFrigo($ingredient);
        $em->flush();

        return $this->json(['message' => 'Ingrédient supprimé du frigo'], 200);
    }
}