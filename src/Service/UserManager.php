<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserManager
{
    public function __construct(private Security $security)
    {
    }

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

    /**
     * Refuse (401) toute requête sans utilisateur authentifié par jeton signé.
     * Défense en profondeur : l'access_control du firewall applique déjà la même règle.
     */
    public function ensureAuthenticated(Request $request): ?JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return null;
        }

        if (!$this->security->getUser() instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return null;
    }

    /** L'utilisateur connecté est administrateur. */
    public function isAdmin(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    /** L'utilisateur connecté est ce compte, ou un administrateur. */
    public function canAccessUserId(?int $userId): bool
    {
        $current = $this->security->getUser();

        return $current instanceof User && ($this->isAdmin() || ($userId !== null && $current->getId() === $userId));
    }

    public function forbidden(): JsonResponse
    {
        return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
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
