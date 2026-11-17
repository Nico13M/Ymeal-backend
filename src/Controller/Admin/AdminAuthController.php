<?php

namespace App\Controller\Admin;

use App\Security\ApiTokenService;
use App\Service\AuthManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/auth', name: 'admin_auth_')]
class AdminAuthController extends AbstractController
{
    public function __construct(
        private AuthManager $authManager,
        private ApiTokenService $tokenService,
    ) {}

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $result = $this->authManager->register(is_array($data) ? $data : []);
        if (isset($result['error'])) {
            return new JsonResponse(['error' => $result['error']], $result['code']);
        }
        $user = $result['user'];
        return new JsonResponse([
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'pseudo' => $user->getPseudo(),
                    'lastName' => $user->getLastName(),
                ],
                'token' => $this->tokenService->issue($user),
            ],
            'message' => $result['message'],
            'code' => $result['code']
        ], $result['code']);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $result = $this->authManager->login(is_array($data) ? $data : []);
        if (isset($result['error'])) {
            return new JsonResponse(['error' => $result['error']], $result['code']);
        }
        $user = $result['user'];
        return new JsonResponse([
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'pseudo' => $user->getPseudo(),
                    'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'lastName' => $user->getLastName(),
                ],
                'token' => $this->tokenService->issue($user),
                'token_type' => 'Bearer',
                'expires_in' => ApiTokenService::TTL,
            ],
            'message' => 'Login successful',
            'code' => 200
        ]);
    }
}
