<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthManager
{
    public const MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    /** E-mail normalisé : sans espaces, en minuscules. */
    public static function normalizeEmail(mixed $email): string
    {
        return is_string($email) ? mb_strtolower(trim($email)) : '';
    }

    public function register(array $data): array
    {
        $firstname = trim((string) ($data['firstname'] ?? ''));
        $lastname = trim((string) ($data['lastname'] ?? ''));
        $email = self::normalizeEmail($data['email'] ?? '');
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';
        $pseudo = trim((string) ($data['pseudo'] ?? ''));

        if (!$firstname || !$lastname || !$email || !$password) {
            return ['error' => 'Missing fields: firstname, lastname, email, password required', 'code' => 400];
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 255) {
            return ['error' => 'Invalid email', 'code' => 400];
        }
        if (mb_strlen($firstname) > 45 || mb_strlen($lastname) > 45) {
            return ['error' => 'Firstname and lastname must be 45 characters max', 'code' => 400];
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return ['error' => 'Password too short (min ' . self::MIN_PASSWORD_LENGTH . ' characters)', 'code' => 400];
        }
        if (strlen($password) > 4096) {
            return ['error' => 'Password too long', 'code' => 400];
        }
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return ['error' => 'Email already used', 'code' => 400];
        }

        $user = new User();
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $user->setEmail($email);
        if ($pseudo === '') {
            $pseudo = strstr($email, '@', true) ?: $firstname;
        }
        $user->setPseudo(mb_substr($pseudo, 0, 45));
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
        $user->setCreatedAt(new \DateTime());
        $user->setUpdatedAt(new \DateTime());

        try {
            $this->em->persist($user);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Deux inscriptions simultanées avec le même e-mail : l'index unique tranche
            return ['error' => 'Email already used', 'code' => 400];
        }

        return ['user' => $user, 'message' => 'User registered', 'code' => 201];
    }

    /**
     * Vérifie les identifiants. Le message d'erreur est identique que le compte existe ou non,
     * pour ne pas permettre d'énumérer les comptes.
     */
    public function login(array $data): array
    {
        $email = self::normalizeEmail($data['email'] ?? '');
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';

        $user = $email !== '' ? $this->em->getRepository(User::class)->findOneBy(['email' => $email]) : null;

        if ($user === null) {
            // Même coût de calcul que pour un vrai compte, pour ne pas révéler son existence par le temps de réponse
            $this->passwordHasher->hashPassword(new User(), $password);

            return ['error' => 'Invalid credentials', 'code' => 401];
        }
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return ['error' => 'Invalid credentials', 'code' => 401];
        }

        return ['user' => $user, 'message' => 'Login successful', 'code' => 200];
    }
}
