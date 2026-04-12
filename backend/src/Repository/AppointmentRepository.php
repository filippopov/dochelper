<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    public function save(Appointment $appointment, bool $flush = true): void
    {
        $this->getEntityManager()->persist($appointment);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<Appointment>
     */
    public function findForUser(User $user): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.scheduledAt', 'DESC');

        if ($user->isDoctor()) {
            $qb->andWhere('a.doctor = :user');
        } else {
            $qb->andWhere('a.patient = :user');
        }

        return $qb->setParameter('user', $user)->getQuery()->getResult();
    }
}
