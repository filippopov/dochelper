<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DoctorAvailability;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DoctorAvailability>
 */
class DoctorAvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DoctorAvailability::class);
    }

    public function save(DoctorAvailability $availability, bool $flush = true): void
    {
        $this->getEntityManager()->persist($availability);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DoctorAvailability $availability, bool $flush = true): void
    {
        $this->getEntityManager()->remove($availability);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<DoctorAvailability>
     */
    public function findForDoctor(User $doctor): array
    {
        return $this->createQueryBuilder('da')
            ->andWhere('da.doctor = :doctor')
            ->setParameter('doctor', $doctor)
            ->orderBy('da.dayOfWeek', 'ASC')
            ->addOrderBy('da.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<DoctorAvailability>
     */
    public function findForDoctorAndDay(User $doctor, int $dayOfWeek): array
    {
        return $this->createQueryBuilder('da')
            ->andWhere('da.doctor = :doctor')
            ->andWhere('da.dayOfWeek = :dayOfWeek')
            ->setParameter('doctor', $doctor)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->orderBy('da.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOwnedByDoctor(User $doctor, int $availabilityId): ?DoctorAvailability
    {
        return $this->createQueryBuilder('da')
            ->andWhere('da.id = :availabilityId')
            ->andWhere('da.doctor = :doctor')
            ->setParameter('availabilityId', $availabilityId)
            ->setParameter('doctor', $doctor)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
