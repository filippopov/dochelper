<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity(fields: ['email'], message: 'Email already exists.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_TYPE_PATIENT = 'patient';
    public const ROLE_TYPE_DOCTOR = 'doctor';
    public const ROLE_TYPE_ADMIN = 'admin';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column(length: 80)]
    private string $firstName = '';

    #[ORM\Column(length: 80)]
    private string $lastName = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 20)]
    private string $roleType = self::ROLE_TYPE_PATIENT;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->setRoleType(self::ROLE_TYPE_PATIENT);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower($email);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getRoleType(): string
    {
        return $this->roleType;
    }

    public function setRoleType(string $roleType): self
    {
        $normalized = mb_strtolower($roleType);

        if (!in_array($normalized, [self::ROLE_TYPE_PATIENT, self::ROLE_TYPE_DOCTOR, self::ROLE_TYPE_ADMIN], true)) {
            throw new \InvalidArgumentException('Unsupported role type.');
        }

        $this->roleType = $normalized;
        $this->roles = match ($normalized) {
            self::ROLE_TYPE_DOCTOR => ['ROLE_USER', 'ROLE_DOCTOR'],
            self::ROLE_TYPE_ADMIN => ['ROLE_USER', 'ROLE_ADMIN'],
            default => ['ROLE_USER', 'ROLE_PATIENT'],
        };

        return $this;
    }

    public function isDoctor(): bool
    {
        return $this->roleType === self::ROLE_TYPE_DOCTOR;
    }

    public function isPatient(): bool
    {
        return $this->roleType === self::ROLE_TYPE_PATIENT;
    }

    public function isAdmin(): bool
    {
        return $this->roleType === self::ROLE_TYPE_ADMIN;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
