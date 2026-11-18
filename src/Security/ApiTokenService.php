<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Émet et vérifie les jetons d'accès de l'API.
 *
 * Format : base64url(payload JSON) . "." . base64url(HMAC-SHA256)
 * Le payload contient l'id utilisateur, la date d'expiration et une empreinte du hash
 * du mot de passe : changer de mot de passe invalide donc tous les jetons déjà émis.
 */
class ApiTokenService
{
    /** Durée de vie d'un jeton : 7 jours. */
    public const TTL = 604800;

    private const MIN_SECRET_LENGTH = 32;

    public function __construct(#[Autowire('%kernel.secret%')] private readonly string $secret)
    {
        // Sans secret solide, les jetons seraient falsifiables : on refuse de démarrer.
        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new \LogicException(sprintf(
                'APP_SECRET doit faire au moins %d caractères pour signer les jetons d\'API.',
                self::MIN_SECRET_LENGTH
            ));
        }
    }

    public function issue(User $user, ?int $now = null): string
    {
        $payload = $this->encode(json_encode([
            'uid' => $user->getId(),
            'exp' => ($now ?? time()) + self::TTL,
            'pwd' => $this->passwordFingerprint($user),
        ], JSON_THROW_ON_ERROR));

        return $payload . '.' . $this->encode($this->sign($payload));
    }

    /**
     * @return array{uid: int, exp: int, pwd: string}|null null si le jeton est invalide ou expiré
     */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$payload, $signature] = $parts;

        $given = $this->decode($signature);
        if ($given === null || !hash_equals($this->sign($payload), $given)) {
            return null;
        }

        $json = $this->decode($payload);
        $data = $json === null ? null : json_decode($json, true);
        if (!is_array($data)
            || !is_int($data['uid'] ?? null)
            || !is_int($data['exp'] ?? null)
            || !is_string($data['pwd'] ?? null)
            || $data['exp'] < time()
        ) {
            return null;
        }

        return $data;
    }

    /** Vrai si le jeton a été émis pour l'état actuel du mot de passe de l'utilisateur. */
    public function matchesUser(array $payload, User $user): bool
    {
        return hash_equals($this->passwordFingerprint($user), $payload['pwd']);
    }

    private function passwordFingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', 'pwd|' . $user->getPasswordHash(), $this->secret), 0, 16);
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', 'ymeal-api-token|' . $payload, $this->secret, true);
    }

    private function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function decode(string $encoded): ?string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
