<?php

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/register')]
class RegistrationController extends AbstractController
{
    #[Route('/api/register', name: 'api_admin_register', methods: ['POST'])]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $firstname = $data['firstname'] ?? '';
        $lastname = $data['lastname'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (!$firstname || !$lastname || !$email || !$password) {
            return new JsonResponse(['error' => 'Missing fields: firstname, lastname, email, password required'], 400);
        }

        if (strlen($password) < 6) {
            return new JsonResponse(['error' => 'Password too short (min 6 characters)'], 400);
        }

        $repo = $em->getRepository(User::class);
        $existing = $repo->findOneBy(['email' => $email]);
        if ($existing) {
            return new JsonResponse(['error' => 'Email already used'], 400);
        }

        $user = new User();
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $user->setEmail($email);

        // Hash the password using Symfony's UserPasswordHasherInterface
        $hash = $passwordHasher->hashPassword($user, $password);
        $user->setPasswordHash($hash);

        // Ensure createdAt is set (in case Gedmo timestampable listener is not active)
        if (method_exists($user, 'setCreatedAt')) {
            $user->setCreatedAt(new \DateTime());
        }
        if (method_exists($user, 'setUpdatedAt')) {
            $user->setUpdatedAt(new \DateTime());
        }

        $em->persist($user);
        $em->flush();

        return new JsonResponse(['message' => 'User registered'], 201);
    }
}
