<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authentifie les requêtes portant un jeton signé : "Authorization: Bearer <jeton>".
 */
class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ApiTokenService $tokenService,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $this->extractToken($request) !== null;
    }

    public function authenticate(Request $request): Passport
    {
        $payload = $this->tokenService->verify((string) $this->extractToken($request));
        if ($payload === null) {
            throw new AuthenticationException('Invalid token');
        }

        return new SelfValidatingPassport(new UserBadge((string) $payload['uid'], function (string $uid) use ($payload) {
            $user = $this->userRepository->find((int) $uid);
            // Jeton révoqué si l'utilisateur n'existe plus ou a changé de mot de passe
            if ($user === null || !$this->tokenService->matchesUser($payload, $user)) {
                throw new AuthenticationException('Invalid token');
            }

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Message volontairement générique : pas de détail sur la raison du refus
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
    }

    /** Appelé quand une route protégée est atteinte sans authentification. */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
    }

    private function extractToken(Request $request): ?string
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
