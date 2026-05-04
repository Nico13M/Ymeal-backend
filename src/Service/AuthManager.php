<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class AuthManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private UserProviderInterface $userProvider,
        private TokenStorageInterface $tokenStorage,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function register(array $data): array
    {
        $firstname = $data['firstname'] ?? '';
        $lastname = $data['lastname'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (!$firstname || !$lastname || !$email || !$password) {
            return ['error' => 'Missing fields: firstname, lastname, email, password required', 'code' => 400];
        }
        if (strlen($password) < 6) {
            return ['error' => 'Password too short (min 6 characters)', 'code' => 400];
        }
        $repo = $this->em->getRepository(User::class);
        $existing = $repo->findOneBy(['email' => $email]);
        if ($existing) {
            return ['error' => 'Email already used', 'code' => 400];
        }
        $user = new User();
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $user->setEmail($email);
        $hash = $this->passwordHasher->hashPassword($user, $password);
        $user->setPasswordHash($hash);
        if (method_exists($user, 'setCreatedAt')) {
            $user->setCreatedAt(new \DateTime());
        }
        if (method_exists($user, 'setUpdatedAt')) {
            $user->setUpdatedAt(new \DateTime());
        }
        $this->em->persist($user);
        $this->em->flush();
        return ['user' => $user, 'message' => 'User registered', 'code' => 201];
    }

    public function login(array $data, Request $request): array
    {
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        try {
            $user = $this->userProvider->loadUserByIdentifier($email);
            if (!$user instanceof \Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface) {
                throw new AuthenticationException('User cannot be authenticated with a password.');
            }
            if (!$this->passwordHasher->isPasswordValid($user, $password)) {
                throw new AuthenticationException('Invalid credentials');
            }
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->tokenStorage->setToken($token);
            $event = new InteractiveLoginEvent($request, $token);
            $this->eventDispatcher->dispatch($event);
            return ['user' => $user, 'message' => 'Login successful', 'code' => 200];
        } catch (AuthenticationException $e) {
            return ['error' => $e->getMessage(), 'code' => 401];
        }
    }
}
