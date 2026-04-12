<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;

class RefreshTokenService
{
    private const REFRESH_TTL_SECONDS = 1209600;

    public function __construct(private readonly RefreshTokenRepository $refreshTokenRepository)
    {
    }

    /**
     * @return array{plainToken: string, expiresAt: \DateTimeImmutable}
     */
    public function issueForUser(User $user): array
    {
        $plainToken = bin2hex(random_bytes(64));
        $expiresAt = new \DateTimeImmutable('+' . self::REFRESH_TTL_SECONDS . ' seconds');

        $refreshToken = (new RefreshToken())
            ->setUser($user)
            ->setTokenHash($this->hashToken($plainToken))
            ->setExpiresAt($expiresAt);

        $this->refreshTokenRepository->save($refreshToken);

        return [
            'plainToken' => $plainToken,
            'expiresAt' => $expiresAt,
        ];
    }

    public function consumeAndRotate(string $plainToken): ?array
    {
        $tokenHash = $this->hashToken($plainToken);
        $stored = $this->refreshTokenRepository->findActiveByTokenHash($tokenHash);

        if (!$stored instanceof RefreshToken) {
            return null;
        }

        $user = $stored->getUser();
        $stored->revoke();
        $this->refreshTokenRepository->save($stored);

        $new = $this->issueForUser($user);

        return [
            'user' => $user,
            'refreshToken' => $new['plainToken'],
            'refreshExpiresAt' => $new['expiresAt'],
        ];
    }

    public function revokeByPlainToken(string $plainToken): void
    {
        $tokenHash = $this->hashToken($plainToken);
        $stored = $this->refreshTokenRepository->findOneBy(['tokenHash' => $tokenHash, 'revokedAt' => null]);

        if ($stored instanceof RefreshToken) {
            $stored->revoke();
            $this->refreshTokenRepository->save($stored);
        }
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
