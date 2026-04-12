<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function save(RefreshToken $refreshToken, bool $flush = true): void
    {
        $this->getEntityManager()->persist($refreshToken);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveByTokenHash(string $tokenHash): ?RefreshToken
    {
        $token = $this->findOneBy(['tokenHash' => $tokenHash, 'revokedAt' => null]);

        if (!$token instanceof RefreshToken || !$token->isActive()) {
            return null;
        }

        return $token;
    }
}
