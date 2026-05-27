<?php
namespace App\Controller\Admin;

use App\Entity\Frigo;
use App\Entity\FrigoIngredient;
use App\Entity\Ingredient;
use App\Entity\User;
use App\Repository\IngredientRepository;
use App\Repository\UnitsRepository;
use App\Repository\UserRepository;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    private function formatFrigoIngredient(FrigoIngredient $fi): array
    {
        $ing = $fi->getIngredient();
        $unit = $fi->getUnit();
        return [
            'id'       => $ing->getId(),
            'name'     => $ing->getName(),
            'slug'     => $ing->getSlug(),
            'quantity' => $fi->getQuantity(),
            'unit'     => $unit ? [
                'id'     => $unit->getId(),
                'name'   => $unit->getName(),
                'symbol' => $unit->getSymbol(),
            ] : null,
        ];
    }

    // --- 0. LISTER TOUS LES INGRÉDIENTS DISPONIBLES ---
    #[Route('/ingredients', name: 'list_ingredients', methods: ['GET'])]
    public function listIngredients(Request $request, IngredientRepository $ingredientRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        return $this->json($ingredientRepository->ingredientFindOnly10());
    }  

    #[Route('/ingredients/search', name: 'search_ingredients', methods: ['GET'])]
    public function searchIngredients(Request $request, IngredientRepository $ingredientRepository): JsonResponse
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $query = trim((string) $request->query->get('query', ''));

        if ($query === '') {
            return $this->json([]);
        }

        $ingredients = $ingredientRepository->ingredientFindByName($query);
        return $this->json($ingredients);
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

        $data = array_map(
            fn(FrigoIngredient $fi) => $this->formatFrigoIngredient($fi),
            $frigo->getFrigoIngredients()->toArray()
        );

        return $this->json($data);
    }

    // --- 2. AJOUTER / METTRE À JOUR UN INGRÉDIENT DU FRIGO ---
    // Body JSON attendu : { "quantity": 2.5, "unit_id": 1 }
    #[Route('/{id}', name: 'add_ingredient', methods: ['POST'])]
    public function addIngredient(
        Request $request,
        Ingredient $ingredient,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        UnitsRepository $unitsRepository
    ): Response {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }

        $user = $this->resolveUser($request, $userRepository);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $frigo = $this->getOrCreateFrigo($user, $em);

        $body = json_decode($request->getContent(), true) ?? [];
        $quantity = isset($body['quantity']) ? (float) $body['quantity'] : 1.0;
        $unitId   = $body['unit_id'] ?? null;
        $unit     = $unitId ? $unitsRepository->find((int) $unitId) : null;

        // Mise à jour si déjà présent
        $existing = $frigo->getFrigoIngredientFor($ingredient);
        if ($existing) {
            $existing->setQuantity($quantity);
            if ($unit) $existing->setUnit($unit);
            $em->flush();
            return $this->json(['message' => 'Ingrédient mis à jour', 'item' => $this->formatFrigoIngredient($existing)], 200);
        }

        $fi = new FrigoIngredient();
        $fi->setFrigo($frigo);
        $fi->setIngredient($ingredient);
        $fi->setQuantity($quantity);
        $fi->setUnit($unit);

        $em->persist($fi);
        $em->flush();

        return $this->json(['message' => 'Ingrédient ajouté', 'item' => $this->formatFrigoIngredient($fi)], 201);
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

        $fi = $frigo->getFrigoIngredientFor($ingredient);
        if (!$fi) {
            return $this->json(['message' => 'Ingrédient non trouvé dans le frigo'], 404);
        }

        $frigo->removeFrigoIngredient($fi);
        $em->remove($fi);
        $em->flush();

        return $this->json(['message' => 'Ingrédient supprimé'], 200);
    }

     #[Route('/count', name: 'count', methods: ['GET'])]
    public function count(Request $request): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }
 
        $user = $this->getUser();
        assert($user instanceof User);
 
        $frigo = $user->getFrigo();
 
        // L'utilisateur n'a pas encore de frigo créé
        if ($frigo === null) {
            return $this->json([
                'count'     => 0,
                'has_frigo' => false,
            ]);
        }
 
        $count = $this->frigoIngredientRepository->countByFrigo($frigo->getId());
 
        return $this->json([
            'count'     => $count,
            'has_frigo' => true,
        ]);
    }
}