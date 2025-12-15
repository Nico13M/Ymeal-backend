<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserManager
{
    
       public function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'email' => $user->getEmail(),
            'createdAt' => $user->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $user->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }

    public function ensureAuthenticated(Request $request): ?JsonResponse
    {
        // Simple token-based check for APIs. Configure env var API_TOKEN.
        $expected = $_ENV['API_TOKEN'] ?? getenv('API_TOKEN');

        // If no token configured, skip check (convenience for local/dev).
        if (!$expected) {
            return null;
        }

        $auth = $request->headers->get('authorization') ?: $request->headers->get('x-api-key');
        if (!$auth) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Accept both "Bearer TOKEN" and raw token in header
        if (str_starts_with(strtolower($auth), 'bearer ')) {
            $token = substr($auth, 7);
        } else {
            $token = $auth;
        }

        if (!hash_equals($expected, $token)) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return null;
    }

    public function getFormErrors(\Symfony\Component\Form\FormInterface $form): array
    {
        $errors = [];

        foreach ($form->getErrors(true) as $error) {
            $formField = $error->getOrigin()?->getName() ?: 'form';
            $errors[$formField][] = $error->getMessage();
        }

        return $errors;
    }
}
