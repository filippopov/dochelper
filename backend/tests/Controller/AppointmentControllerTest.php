<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppointmentControllerTest extends WebTestCase
{
    public function testAppointmentListRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/appointments');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAppointmentCreateRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/appointments', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'doctorId' => 1,
            'scheduledAt' => (new \DateTimeImmutable('+1 day'))->format(DATE_ATOM),
            'durationMinutes' => 30,
            'reason' => 'Initial consultation',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
    }

    public function testDoctorCanApproveOwnPendingAppointment(): void
    {
        $client = static::createClient();
        $doctorEmail = 'doctor.approve.' . uniqid('', true) . '@example.com';
        $doctor = $this->createUser($doctorEmail, 'secret123', User::ROLE_TYPE_DOCTOR);
        $patient = $this->createUser('patient.approve.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_PATIENT);
        $appointment = $this->createAppointment($patient, $doctor, Appointment::STATUS_PENDING);

        $token = $this->loginAndGetToken($client, $doctorEmail, 'secret123');
        $client->request('PATCH', '/api/appointments/' . $appointment->getId() . '/status', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'status' => Appointment::STATUS_CONFIRMED,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(200);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(Appointment::STATUS_CONFIRMED, $payload['status']);
    }

    public function testAdminCanApprovePendingAppointment(): void
    {
        $client = static::createClient();
        $doctor = $this->createUser('doctor.admin-approve.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_DOCTOR);
        $patient = $this->createUser('patient.admin-approve.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_PATIENT);
        $adminEmail = 'admin.approve.' . uniqid('', true) . '@example.com';
        $this->createUser($adminEmail, 'secret123', User::ROLE_TYPE_ADMIN);
        $appointment = $this->createAppointment($patient, $doctor, Appointment::STATUS_PENDING);

        $token = $this->loginAndGetToken($client, $adminEmail, 'secret123');
        $client->request('PATCH', '/api/appointments/' . $appointment->getId() . '/status', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'status' => Appointment::STATUS_CONFIRMED,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(200);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(Appointment::STATUS_CONFIRMED, $payload['status']);
    }

    public function testDoctorCannotApproveAnotherDoctorAppointment(): void
    {
        $client = static::createClient();
        $doctorAEmail = 'doctor.a.approve.' . uniqid('', true) . '@example.com';
        $doctorA = $this->createUser($doctorAEmail, 'secret123', User::ROLE_TYPE_DOCTOR);
        $doctorB = $this->createUser('doctor.b.approve.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_DOCTOR);
        $patient = $this->createUser('patient.other-doctor.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_PATIENT);
        $appointment = $this->createAppointment($patient, $doctorB, Appointment::STATUS_PENDING);

        self::assertNotSame($doctorA->getId(), $doctorB->getId());

        $token = $this->loginAndGetToken($client, $doctorAEmail, 'secret123');
        $client->request('PATCH', '/api/appointments/' . $appointment->getId() . '/status', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'status' => Appointment::STATUS_CONFIRMED,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(403);
    }

    public function testDoctorCanCancelOwnAppointment(): void
    {
        $client = static::createClient();
        $doctorEmail = 'doctor.cancel.' . uniqid('', true) . '@example.com';
        $doctor = $this->createUser($doctorEmail, 'secret123', User::ROLE_TYPE_DOCTOR);
        $patient = $this->createUser('patient.cancel.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_PATIENT);
        $appointment = $this->createAppointment($patient, $doctor, Appointment::STATUS_PENDING);

        $token = $this->loginAndGetToken($client, $doctorEmail, 'secret123');
        $client->request('POST', '/api/appointments/' . $appointment->getId() . '/cancel', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(200);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(Appointment::STATUS_CANCELLED, $payload['status']);
    }

    public function testAdminCanCancelAnyAppointment(): void
    {
        $client = static::createClient();
        $doctor = $this->createUser('doctor.admin-cancel.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_DOCTOR);
        $patient = $this->createUser('patient.admin-cancel.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_PATIENT);
        $adminEmail = 'admin.cancel.' . uniqid('', true) . '@example.com';
        $this->createUser($adminEmail, 'secret123', User::ROLE_TYPE_ADMIN);
        $appointment = $this->createAppointment($patient, $doctor, Appointment::STATUS_CONFIRMED);

        $token = $this->loginAndGetToken($client, $adminEmail, 'secret123');
        $client->request('POST', '/api/appointments/' . $appointment->getId() . '/cancel', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(200);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(Appointment::STATUS_CANCELLED, $payload['status']);
    }

    public function testAdminCannotMarkAppointmentCompleted(): void
    {
        $client = static::createClient();
        $doctor = $this->createUser('doctor.admin-complete.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_DOCTOR);
        $patient = $this->createUser('patient.admin-complete.' . uniqid('', true) . '@example.com', 'secret123', User::ROLE_TYPE_PATIENT);
        $adminEmail = 'admin.complete.' . uniqid('', true) . '@example.com';
        $this->createUser($adminEmail, 'secret123', User::ROLE_TYPE_ADMIN);
        $appointment = $this->createAppointment($patient, $doctor, Appointment::STATUS_CONFIRMED);

        $token = $this->loginAndGetToken($client, $adminEmail, 'secret123');
        $client->request('PATCH', '/api/appointments/' . $appointment->getId() . '/status', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'status' => Appointment::STATUS_COMPLETED,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testPatientCannotCreateSecondAppointmentSameDayWithDoctor(): void
    {
        $client = static::createClient();
        $doctorEmail = 'doctor.same-day.' . uniqid('', true) . '@example.com';
        $doctor = $this->createUser($doctorEmail, 'secret123', User::ROLE_TYPE_DOCTOR);
        $patientEmail = 'patient.same-day.' . uniqid('', true) . '@example.com';
        $this->createUser($patientEmail, 'secret123', User::ROLE_TYPE_PATIENT);

        $token = $this->loginAndGetToken($client, $patientEmail, 'secret123');
        $appointmentDay = new \DateTimeImmutable('+1 day');

        $client->request('POST', '/api/appointments', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'doctorId' => $doctor->getId(),
            'scheduledAt' => $appointmentDay->setTime(10, 0)->format(DATE_ATOM),
            'durationMinutes' => 30,
            'reason' => 'Initial consultation',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/appointments', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'doctorId' => $doctor->getId(),
            'scheduledAt' => $appointmentDay->setTime(14, 0)->format(DATE_ATOM),
            'durationMinutes' => 30,
            'reason' => 'Follow-up consultation',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(409);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('APPOINTMENT_ALREADY_EXISTS_FOR_DAY', $payload['code']);
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

    private function createAppointment(User $patient, User $doctor, string $status): Appointment
    {
        $container = static::getContainer();
        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $container->get(AppointmentRepository::class);

        $appointment = (new Appointment())
            ->setPatient($patient)
            ->setDoctor($doctor)
            ->setScheduledAt(new \DateTimeImmutable('+2 days'))
            ->setDurationMinutes(30)
            ->setReason('Follow-up')
            ->setStatus($status);

        $appointmentRepository->save($appointment);

        return $appointment;
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
