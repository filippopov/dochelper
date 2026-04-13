<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->persist($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findDoctorById(int $id): ?User
    {
        $user = $this->find($id);

        if (!$user instanceof User || !$user->isDoctor()) {
            return null;
        }

        return $user;
    }

    /**
     * @return list<User>
     */
    public function findAllDoctors(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roleType = :roleType')
            ->setParameter('roleType', User::ROLE_TYPE_DOCTOR)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
