<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\UserPreferenceService;
use App\Service\UserManager;
use Symfony\Component\HttpFoundation\Request;
use App\Service\CsrfService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin/user/preferences', name: 'admin_user_preferences_')]
class AdminUserPreferencesController extends AbstractController
{
    // ✅ ADD USERMANAGER INJECTION
    public function __construct(
        private UserPreferenceService $preferencesService,
        private CsrfService $csrfService,
        private UserManager $userManager,  // ✅ ADD THIS
    ) {}

    // ============= HELPER PRIVÉ =============

    private function resolveUser(Request $request): User|Response
    {
        if ($request->isMethod('POST')) {
            $csrfToken = $request->headers->get('X-CSRF-TOKEN');
            if (!$this->csrfService->isValid('api', $csrfToken)) {
                return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
            }
        }

        // ✅ NOW THIS WORKS - userManager is injected
        if ($err = $this->userManager->ensureAuthenticated($request)) return $err;

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        return $user;
    }

    // ============= HELPER - RETURN JSON (no userId wrapper) =============
    
    private function jsonResponse($data): Response
    {
        // ✅ Return plain data, not wrapped
        return $this->json($data);
    }

    // ============= RÉGIMES =============

    #[Route('/diets', name: 'diets_get', methods: ['GET'])]
    public function getDiets(Request $request): Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof Response) return $user;

        return $this->jsonResponse($this->preferencesService->getDiets($user));
    }

    #[Route('/diets', name: 'diets_set', methods: ['POST'])]
    public function setDiets(Request $request): Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof Response) return $user;

        $body = json_decode($request->getContent(), true);
        $dietIds = $body['diet_ids'] ?? [];

        $this->preferencesService->setDiets($user, $dietIds);

        return $this->jsonResponse(['message' => 'Régimes mis à jour']);
    }

    // ============= BUDGET =============

    #[Route('/budget', name: 'budget_get', methods: ['GET'])]
    public function getBudget(Request $request): Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof Response) return $user;

        return $this->jsonResponse($this->preferencesService->getBudget($user));
    }

    #[Route('/budget', name: 'budget_set', methods: ['POST'])]
    public function setBudget(Request $request): Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof Response) return $user;

        $body = json_decode($request->getContent(), true);
        $amount = $body['amount'] ?? null;

        if ($amount === null || !is_numeric($amount) || $amount < 0) {
            return $this->json(['error' => 'Montant invalide'], Response::HTTP_BAD_REQUEST);
        }

        return $this->jsonResponse($this->preferencesService->setBudget($user, (float) $amount));
    }

    // ============= NOMBRE DE PERSONNES =============

    #[Route('/person-count', name: 'person_count_get', methods: ['GET'])]
    public function getPersonCount(Request $request): Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof Response) return $user;

        return $this->jsonResponse($this->preferencesService->getPersonCount($user));
    }

    #[Route('/person-count', name: 'person_count_set', methods: ['POST'])]
    public function setPersonCount(Request $request): Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof Response) return $user;

        $body = json_decode($request->getContent(), true);
        $count = $body['count'] ?? null;

        if (!is_int($count) || $count < 1) {
            return $this->json(['error' => 'Nombre de personnes invalide'], Response::HTTP_BAD_REQUEST);
        }

        return $this->jsonResponse($this->preferencesService->setPersonCount($user, $count));
    }

    // ============= CUISINES FAVORITES =============

    #[Route('/favorite-cuisines', name: 'favorite_cuisines_get', methods: ['GET'])]
    public function getFavoriteCuisines(Request $request): Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof Response) return $user;

        return $this->jsonResponse($this->preferencesService->getFavoriteCuisines($user));
    }

    #[Route('/favorite-cuisines', name: 'favorite_cuisines_set', methods: ['POST'])]
    public function setFavoriteCuisines(Request $request): Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof Response) return $user;

        $body = json_decode($request->getContent(), true);
        $this->preferencesService->setFavoriteCuisines($user, $body['cuisine_ids'] ?? []);

        return $this->jsonResponse(['message' => 'Cuisines favorites mises à jour']);
    }

    // ============= BLACKLIST =============

    #[Route('/blacklist', name: 'blacklist_get', methods: ['GET'])]
    public function getBlacklist(Request $request): Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof Response) return $user;

        return $this->jsonResponse($this->preferencesService->getBlacklist($user));
    }

    #[Route('/blacklist', name: 'blacklist_set', methods: ['POST'])]
    public function setBlacklist(Request $request): Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof Response) return $user;

        $body = json_decode($request->getContent(), true);
        $this->preferencesService->setBlacklist($user, $body['ingredient_ids'] ?? []);

        return $this->jsonResponse(['message' => 'Blacklist mise à jour']);
    }

    // ============= ALLERGIES =============

    #[Route('/allergies', name: 'allergies_get', methods: ['GET'])]
    public function getAllergies(Request $request): Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof Response) return $user;

        return $this->jsonResponse($this->preferencesService->getAllergies($user));
    }

    #[Route('/allergies', name: 'allergies_set', methods: ['POST'])]
    public function setAllergies(Request $request): Response
    {
        $user = $this->resolveUser($request);
        if ($user instanceof Response) return $user;

        $body = json_decode($request->getContent(), true);
        $this->preferencesService->setAllergies($user, $body['allergy_ids'] ?? []);

        return $this->jsonResponse(['message' => 'Allergies mises à jour']);
    }
}