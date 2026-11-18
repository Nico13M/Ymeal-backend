<?php

namespace App\Controller\Admin;

use App\Security\ApiTokenService;
use App\Service\AuthManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/auth', name: 'admin_auth_')]
class AdminAuthController extends AbstractController
{
    public function __construct(
        private AuthManager $authManager,
        private ApiTokenService $tokenService,
        private RateLimiterFactory $loginAccountLimiter,
        private RateLimiterFactory $loginIpLimiter,
        private RateLimiterFactory $registerIpLimiter,
    ) {}

    /** 429 avec l'en-tête Retry-After, sans détail sur la raison exacte. */
    private function tooManyRequests(RateLimit $limit): JsonResponse
    {
        $retry = max(1, $limit->getRetryAfter()->getTimestamp() - time());
        return new JsonResponse(
            ['error' => 'Too many attempts, try again later'],
            JsonResponse::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => (string) $retry]
        );
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $limit = $this->registerIpLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            return $this->tooManyRequests($limit);
        }

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
        $data = is_array($data) ? $data : [];

        // Anti force brute : limite par couple IP + e-mail, et par IP tous e-mails confondus.
        $ip = $request->getClientIp() ?? 'unknown';
        $email = mb_substr(AuthManager::normalizeEmail($data['email'] ?? ''), 0, 255);
        $accountLimiter = $this->loginAccountLimiter->create($ip . '|' . $email);
        $ipLimit = $this->loginIpLimiter->create($ip)->consume();
        $accountLimit = $accountLimiter->consume();
        if (!$ipLimit->isAccepted()) {
            return $this->tooManyRequests($ipLimit);
        }
        if (!$accountLimit->isAccepted()) {
            return $this->tooManyRequests($accountLimit);
        }

        $result = $this->authManager->login($data);
        if (isset($result['error'])) {
            return new JsonResponse(['error' => $result['error']], $result['code']);
        }
        $accountLimiter->reset(); // connexion réussie : on repart de zéro pour ce couple IP + e-mail
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
