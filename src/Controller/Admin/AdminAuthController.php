<?php

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\AuthManager;

#[Route('/admin/auth', name: 'admin_auth_')]
class AdminAuthController extends AbstractController
{
    public function __construct(private AuthManager $authManager) {}

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $result = $this->authManager->register($data);
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
            ],
            'message' => $result['message'],
            'code' => $result['code']
        ], $result['code']);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $result = $this->authManager->login($data, $request);
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
                    'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                    'lastName' => $user->getLastName(),
                ],
            ],
            'message' => 'Login successful',
            'code' => 200
        ]);
    }
}
