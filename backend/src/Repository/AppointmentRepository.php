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

    /**
     * @return list<Appointment>
     */
    public function findForDoctorBetween(User $doctor, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.doctor = :doctor')
            ->andWhere('a.scheduledAt >= :start')
            ->andWhere('a.scheduledAt < :end')
            ->andWhere('a.status != :cancelled')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('cancelled', Appointment::STATUS_CANCELLED)
            ->orderBy('a.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasDoctorConflict(User $doctor, \DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        $windowStart = $start->modify('-120 minutes');

        $appointments = $this->createQueryBuilder('a')
            ->andWhere('a.doctor = :doctor')
            ->andWhere('a.scheduledAt < :end')
            ->andWhere('a.scheduledAt >= :windowStart')
            ->andWhere('a.status != :cancelled')
            ->setParameter('doctor', $doctor)
            ->setParameter('end', $end)
            ->setParameter('windowStart', $windowStart)
            ->setParameter('cancelled', Appointment::STATUS_CANCELLED)
            ->orderBy('a.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($appointments as $appointment) {
            $appointmentStart = $appointment->getScheduledAt();
            $appointmentEnd = $appointmentStart->modify('+' . $appointment->getDurationMinutes() . ' minutes');

            if ($appointmentStart < $end && $appointmentEnd > $start) {
                return true;
            }
        }

        return false;
    }

    public function hasAppointmentForPatientDoctorOnDay(User $patient, User $doctor, \DateTimeImmutable $day): bool
    {
        $dayStart = $day->setTime(0, 0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        $result = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.patient = :patient')
            ->andWhere('a.doctor = :doctor')
            ->andWhere('a.scheduledAt >= :dayStart')
            ->andWhere('a.scheduledAt < :dayEnd')
            ->andWhere('a.status != :cancelled')
            ->setParameter('patient', $patient)
            ->setParameter('doctor', $doctor)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->setParameter('cancelled', Appointment::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }
}
