<?php

namespace App\Controller\Admin;

use App\Repository\AllergyRepository;
use App\Repository\DietRepository;
use App\Repository\FavoriteCuisineRepository;
use App\Repository\IngredientRepository;
use App\Entity\User;
use App\Service\UserManager; 
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin/reference', name: 'admin_reference_')]
class AdminReferenceController extends AbstractController
{
    private UserManager $userManager;

    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    // ============= DIETS =============

    #[Route('/diets', name: 'diets', methods: ['GET'])]
    public function getDiets(Request $request, DietRepository $dietRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) return $err;

        $diets = $dietRepository->findBy([], ['name' => 'ASC']);

        return $this->json(array_map(fn($d) => [
            'id'   => $d->getId(),
            'name' => $d->getName(),
        ], $diets));
    }

    // ============= ALLERGIES =============

    #[Route('/allergies', name: 'allergies', methods: ['GET'])]
    public function getAllergies(Request $request, AllergyRepository $allergyRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) return $err;

        $allergies = $allergyRepository->findAll();

        return $this->json(array_map(fn($a) => [
            'id'   => $a->getId(),
            'name' => $a->getName(),
        ], $allergies));
    }

    // ============= FAVORITE CUISINES =============

    #[Route('/favorite-cuisines', name: 'favorite_cuisines', methods: ['GET'])]
    public function getFavoriteCuisines(Request $request, FavoriteCuisineRepository $favoriteCuisineRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) return $err;

        $cuisines = $favoriteCuisineRepository->findAll();

        return $this->json(array_map(fn($c) => [
            'id'   => $c->getId(),
            'name' => $c->getName(),
        ], $cuisines));
    }

    // ============= INGREDIENTS (recherche 3 caractères min) =============

    #[Route('/ingredients/search', name: 'ingredients_search', methods: ['GET'])]
    public function searchIngredients(Request $request, IngredientRepository $ingredientRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) return $err;

        $query = trim($request->query->get('q', ''));

        if (strlen($query) < 3) {
            return $this->json([]);
        }

        $ingredients = $ingredientRepository->searchByName($query);

        return $this->json(array_map(fn($i) => [
            'id'   => $i->getId(),
            'name' => $i->getName(),
        ], $ingredients));
    }
    
    #[Route('/budgets', name: 'budgets', methods: ['GET'])]
    public function getBudgets(Request $request, BudgetRepository $budgetRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) return $err;

        return $this->json(array_map(fn($b) => [
            'id'     => $b->getId(),
            'key'    => $b->getKey(),
            'label'  => $b->getLabel(),
            'amount' => (float) $b->getAmount(),
        ], $budgetRepository->findBy([], ['amount' => 'ASC'])));
    }
}