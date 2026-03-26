<?php

namespace App\Controller\Admin;

use App\Service\SubscribeManager;
use App\Service\CsrfService;
use App\Service\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/subscribe', name: 'admin_subscribre_')]
class AdminSubscribeController extends AbstractController
{
    public function __construct(private UserManager $userManager, private SubscribeManager $subscribeManager)
    {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $subcriptionList = $this->subscribeManager->listAll();
        return $this->json($subcriptionList);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Request $request, int $id): Response
    {
        $subscription = $this->subscribeManager->find($id);
        if (!$subscription) {
            return $this->json(['error' => 'Subscription not found'], 404);
        }

        return $this->json($this->subscribeManager->serialize($subscription));
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['PATCH'])]
    public function edit(
        Request $request,
        int $id,
        CsrfService $csrfService
    ): Response {
        $subscription = $this->subscribeManager->find($id);
        if (!$subscription) {
            return $this->json(['error' => 'Subscription not found'], 404);
        }

        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $subscription = $this->subscribeManager->update($subscription, $data);

        return $this->json($this->subscribeManager->serialize($subscription));
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request, CsrfService $csrfService): Response
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? '';
        $price = isset($data['price']) ? (int)$data['price'] : 0;
        $durationMonths = isset($data['duration_months']) ? (int)$data['duration_months'] : 0;

        if (!$name || !$price || !$durationMonths) {
            return new JsonResponse(['error' => 'Missing fields: name, price, duration_months required'], 400);
        }

        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $subscription = $this->subscribeManager->create($name, $price, $durationMonths);

        return new JsonResponse(['message' => 'Subscription created', 'id' => $subscription->getId()], 201);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        int $id,
        CsrfService $csrfService
    ): JsonResponse {
        $subscription = $this->subscribeManager->find($id);

        if (!$subscription) {
            return $this->json(['error' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        // CSRF (header)
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');

        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(
                ['error' => 'Invalid CSRF token'],
                Response::HTTP_FORBIDDEN
            );
        }

        $this->subscribeManager->delete($subscription);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

}
