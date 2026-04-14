<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DoctorControllerTest extends WebTestCase
{
    public function testDoctorListRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/doctors');

        self::assertResponseStatusCodeSame(401);
    }

    public function testDoctorCalendarRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/doctors/1/calendar');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAdminCanListDoctors(): void
    {
        $client = static::createClient();
        $this->createUser('doctor.list.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_DOCTOR);
        $adminEmail = 'admin.list.' . uniqid('', true) . '@example.com';
        $this->createUser($adminEmail, 'secret123', User::ROLE_TYPE_ADMIN);

        $token = $this->loginAndGetToken($client, $adminEmail, 'secret123');
        $client->request('GET', '/api/doctors', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(200);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('items', $payload);
        self::assertNotEmpty($payload['items']);
    }

    public function testDoctorCannotListDoctors(): void
    {
        $client = static::createClient();
        $doctorEmail = 'doctor.no-list.' . uniqid('', true) . '@example.com';
        $this->createUser($doctorEmail, 'secret123', User::ROLE_TYPE_DOCTOR);

        $token = $this->loginAndGetToken($client, $doctorEmail, 'secret123');
        $client->request('GET', '/api/doctors', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDoctorCanViewOwnCalendar(): void
    {
        $client = static::createClient();
        $doctorEmail = 'doctor.self.' . uniqid('', true) . '@example.com';
        $doctor = $this->createUser($doctorEmail, 'secret123', User::ROLE_TYPE_DOCTOR);

        $token = $this->loginAndGetToken($client, $doctorEmail, 'secret123');
        $client->request('GET', '/api/doctors/' . $doctor->getId() . '/calendar', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(200);
    }

    public function testDoctorCannotViewAnotherDoctorCalendar(): void
    {
        $client = static::createClient();
        $doctorAEmail = 'doctor.a.' . uniqid('', true) . '@example.com';
        $doctorBEmail = 'doctor.b.' . uniqid('', true) . '@example.com';

        $this->createUser($doctorAEmail, 'secret123', User::ROLE_TYPE_DOCTOR);
        $doctorB = $this->createUser($doctorBEmail, 'secret123', User::ROLE_TYPE_DOCTOR);

        $token = $this->loginAndGetToken($client, $doctorAEmail, 'secret123');
        $client->request('GET', '/api/doctors/' . $doctorB->getId() . '/calendar', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanViewAnyDoctorCalendar(): void
    {
        $client = static::createClient();
        $doctor = $this->createUser('doctor.admin-view.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_DOCTOR);
        $adminEmail = 'admin.calendar.' . uniqid('', true) . '@example.com';
        $this->createUser($adminEmail, 'secret123', User::ROLE_TYPE_ADMIN);

        $token = $this->loginAndGetToken($client, $adminEmail, 'secret123');
        $client->request('GET', '/api/doctors/' . $doctor->getId() . '/calendar', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(200);
    }

    private function createUser(string $email, string $plainPassword, string $roleType): User
    {
        $container = static::getContainer();
        /** @var UserRepository $userRepository */
        $userRepository = $container->get(UserRepository::class);
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = (new User())
            ->setEmail($email)
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
