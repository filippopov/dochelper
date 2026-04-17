<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DoctorAvailabilityOverride;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DoctorAvailabilityOverride>
 */
class DoctorAvailabilityOverrideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DoctorAvailabilityOverride::class);
    }

    public function save(DoctorAvailabilityOverride $override, bool $flush = true): void
    {
        $this->getEntityManager()->persist($override);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DoctorAvailabilityOverride $override, bool $flush = true): void
    {
        $this->getEntityManager()->remove($override);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<DoctorAvailabilityOverride>
     */
    public function findForDoctorAndDate(User $doctor, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('dao')
            ->andWhere('dao.doctor = :doctor')
            ->andWhere('dao.date = :date')
            ->setParameter('doctor', $doctor)
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('dao.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<DoctorAvailabilityOverride>
     */
    public function findForDoctorBetween(User $doctor, \DateTimeImmutable $startDate, \DateTimeImmutable $endDateExclusive): array
    {
        return $this->createQueryBuilder('dao')
            ->andWhere('dao.doctor = :doctor')
            ->andWhere('dao.date >= :startDate')
            ->andWhere('dao.date < :endDateExclusive')
            ->setParameter('doctor', $doctor)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDateExclusive', $endDateExclusive->format('Y-m-d'))
            ->orderBy('dao.date', 'ASC')
            ->addOrderBy('dao.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOwnedByDoctor(User $doctor, int $overrideId): ?DoctorAvailabilityOverride
    {
        return $this->createQueryBuilder('dao')
            ->andWhere('dao.id = :overrideId')
            ->andWhere('dao.doctor = :doctor')
            ->setParameter('overrideId', $overrideId)
            ->setParameter('doctor', $doctor)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
