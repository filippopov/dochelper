<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DoctorAvailabilityOverrideRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctorAvailabilityOverrideRepository::class)]
#[ORM\Table(name: 'app_doctor_availability_override')]
#[ORM\Index(name: 'idx_doctor_availability_override_doctor_date', columns: ['doctor_id', 'date'])]
class DoctorAvailabilityOverride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'doctor_id', nullable: false, onDelete: 'CASCADE')]
    private User $doctor;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'time_immutable')]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(type: 'time_immutable')]
    private \DateTimeImmutable $endTime;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->date = new \DateTimeImmutable('today 00:00:00');
        $this->startTime = new \DateTimeImmutable('09:00:00');
        $this->endTime = new \DateTimeImmutable('17:00:00');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDoctor(): User
    {
        return $this->doctor;
    }

    public function setDoctor(User $doctor): self
    {
        $this->doctor = $doctor;

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): self
    {
        $this->date = $date;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeImmutable $startTime): self
    {
        $this->startTime = $startTime;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeImmutable $endTime): self
    {
        $this->endTime = $endTime;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
