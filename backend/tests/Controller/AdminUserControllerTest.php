<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminUserControllerTest extends WebTestCase
{
    public function testUserListRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/admin/users');

        self::assertResponseStatusCodeSame(401);
    }

    public function testPatientCannotListUsers(): void
    {
        $client = static::createClient();
        $patientEmail = 'patient.users.' . uniqid('', true) . '@example.com';
        $this->createUser($patientEmail, 'secret123', User::ROLE_TYPE_PATIENT, 'Pat', 'Viewer');

        $token = $this->loginAndGetToken($client, $patientEmail, 'secret123');

        $client->request('GET', '/api/admin/users', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanListUsersAndSearch(): void
    {
        $client = static::createClient();
        $adminEmail = 'admin.users.' . uniqid('', true) . '@example.com';
        $this->createUser($adminEmail, 'secret123', User::ROLE_TYPE_ADMIN, 'Admin', 'Manager');

        $needleEmail = 'needle.user.' . uniqid('', true) . '@example.com';
        $this->createUser($needleEmail, 'secret123', User::ROLE_TYPE_PATIENT, 'Nina', 'Needle');
        $this->createUser('other.user.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_PATIENT, 'Olive', 'Other');

        $token = $this->loginAndGetToken($client, $adminEmail, 'secret123');

        $client->request('GET', '/api/admin/users?q=needle', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(200);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('items', $payload);
        self::assertIsArray($payload['items']);
        self::assertNotEmpty($payload['items']);

        $emails = array_map(static fn (array $item): string => (string) ($item['email'] ?? ''), $payload['items']);
        self::assertContains($needleEmail, $emails);
    }

    public function testAdminCanUpdateUserRole(): void
    {
        $client = static::createClient();
        $adminEmail = 'admin.role-update.' . uniqid('', true) . '@example.com';
        $this->createUser($adminEmail, 'secret123', User::ROLE_TYPE_ADMIN, 'Alice', 'Admin');
        $target = $this->createUser(
            'patient.role-update.' . uniqid('', true) . '@example.com',
            'secret123',
            User::ROLE_TYPE_PATIENT,
            'Paul',
            'Patient'
        );

        $token = $this->loginAndGetToken($client, $adminEmail, 'secret123');

        $client->request('PATCH', '/api/admin/users/' . $target->getId() . '/role', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'roleType' => User::ROLE_TYPE_DOCTOR,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(200);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(User::ROLE_TYPE_DOCTOR, $payload['roleType']);
    }

    public function testAdminCannotChangeOwnRole(): void
    {
        $client = static::createClient();
        $adminEmail = 'admin.self-role.' . uniqid('', true) . '@example.com';
        $admin = $this->createUser($adminEmail, 'secret123', User::ROLE_TYPE_ADMIN, 'Sara', 'Self');

        $token = $this->loginAndGetToken($client, $adminEmail, 'secret123');

        $client->request('PATCH', '/api/admin/users/' . $admin->getId() . '/role', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'roleType' => User::ROLE_TYPE_DOCTOR,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('SELF_ROLE_CHANGE_NOT_ALLOWED', $payload['code']);
    }

    private function createUser(string $email, string $plainPassword, string $roleType, string $firstName, string $lastName): User
    {
        $container = static::getContainer();
        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setPassword('')
            ->setRoleType($roleType);

        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $userRepository->save($user);

        return $user;
    }

    private function loginAndGetToken($client, string $email, string $password): string
    {
        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $payload);

        return (string) $payload['token'];
    }
}
