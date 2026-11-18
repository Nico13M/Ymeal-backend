<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserEditType;
use App\Repository\UserRepository;
use App\Service\AuthManager;
use App\Service\CsrfService;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/user', name: 'admin_user_')]
class AdminUserController extends AbstractController
{
    public function __construct(private UserManager $userManager)
    {
    }

    /** Liste de tous les comptes : réservée aux administrateurs. */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }
        if (!$this->userManager->isAdmin()) {
            return $this->userManager->forbidden();
        }

        $users = $userRepository->findAll();
        $data = array_map(fn(User $u) => $this->userManager->serializeUser($u), $users);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, User $user): Response
    {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }
        if (!$this->userManager->canAccessUserId($user->getId())) {
            return $this->userManager->forbidden();
        }

        return $this->json($this->userManager->serializeUser($user));
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        CsrfService $csrfService
    ): Response {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }
        if (!$this->userManager->canAccessUserId($user->getId())) {
            return $this->userManager->forbidden();
        }

        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $newPassword = $data['password'] ?? null;
        if ($newPassword !== null && (!is_string($newPassword) || $newPassword === '')) {
            return $this->json(['error' => 'Invalid password'], 400);
        }
        if ($newPassword !== null) {
            if (mb_strlen($newPassword) < AuthManager::MIN_PASSWORD_LENGTH) {
                return $this->json(['error' => 'Password too short (min ' . AuthManager::MIN_PASSWORD_LENGTH . ' characters)'], 422);
            }
            if (strlen($newPassword) > 4096) {
                return $this->json(['error' => 'Password too long'], 422);
            }
        }

        if (isset($data['email'])) {
            $data['email'] = AuthManager::normalizeEmail($data['email']);
        }
        $emailChanges = isset($data['email']) && $data['email'] !== $user->getEmail();

        // Changer son mot de passe ou son e-mail (identifiant de connexion) exige de prouver
        // qu'on connaît le mot de passe actuel : un jeton volé ne suffit plus à prendre le compte.
        $isOwnAccount = $this->getUser() instanceof User && $this->getUser()->getId() === $user->getId();
        if ($isOwnAccount && ($newPassword !== null || $emailChanges)) {
            $current = $data['current_password'] ?? null;
            if (!is_string($current) || !$passwordHasher->isPasswordValid($user, $current)) {
                return $this->json(['error' => 'current_password required and must be valid'], Response::HTTP_FORBIDDEN);
            }
        }

        $form = $this->createForm(UserEditType::class, $user, [
            'csrf_protection' => false,
        ]);
        $form->submit($data, false);

        if (!$form->isValid()) {
            return $this->json([
                'errors' => (string) $form->getErrors(true, false)
            ], 422);
        }

        if ($newPassword !== null) {
            $user->setPasswordHash($passwordHasher->hashPassword($user, $newPassword));
        }

        $em->flush();

        return $this->json($this->userManager->serializeUser($user), 200);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        CsrfService $csrfService
    ): JsonResponse {
        if ($err = $this->userManager->ensureAuthenticated($request)) {
            return $err;
        }
        if (!$this->userManager->canAccessUserId($user->getId())) {
            return $this->userManager->forbidden();
        }

        // Vérification CSRF
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfService->isValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($user);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

}
