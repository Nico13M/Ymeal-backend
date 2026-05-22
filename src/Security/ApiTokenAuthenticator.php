<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function supports(Request $request): ?bool
    {
        // Support both Authorization header and X-User-Id header
        return $request->headers->has('X-User-Id') 
            || $request->headers->has('authorization') 
            || $request->headers->has('x-api-key');
    }

    public function authenticate(Request $request): Passport
    {
        // Try to get user ID from X-User-Id header
        $userId = $request->headers->get('X-User-Id');
        
        if (!$userId) {
            throw new AuthenticationException('No X-User-Id header provided');
        }

        return new SelfValidatingPassport(
            new UserBadge($userId, function($userId) {
                $user = $this->userRepository->find((int) $userId);
                if (!$user) {
                    throw new AuthenticationException('User not found');
                }
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // Allow request to continue
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null; // Return null to let the next authenticator handle it
    }
}
