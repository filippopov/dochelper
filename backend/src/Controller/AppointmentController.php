<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/appointments')]
class AppointmentController extends AbstractController
{
    #[Route('', name: 'api_appointments_create', methods: ['POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function create(
        Request $request,
        UserRepository $userRepository,
        AppointmentRepository $appointmentRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        $patient = $this->requireUser();

        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $violations = $validator->validate($payload, new Assert\Collection([
            'doctorId' => [new Assert\Required([new Assert\Positive()])],
            'scheduledAt' => [new Assert\Required([new Assert\NotBlank()])],
            'durationMinutes' => [new Assert\Optional([new Assert\Range(min: 15, max: 120)])],
            'reason' => [new Assert\Optional([new Assert\Length(min: 3, max: 500)])],
        ]));

        if (count($violations) > 0) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => $this->formatViolations($violations),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $doctor = $userRepository->findDoctorById((int) $payload['doctorId']);
        if (!$doctor instanceof User) {
            return $this->json([
                'error' => 'Doctor not found.',
                'code' => 'DOCTOR_NOT_FOUND',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        try {
            $scheduledAt = new \DateTimeImmutable((string) $payload['scheduledAt']);
        } catch (\Exception) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => [
                    [
                        'field' => 'scheduledAt',
                        'message' => 'This value is not a valid datetime.',
                    ],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($scheduledAt <= new \DateTimeImmutable()) {
            return $this->json([
                'error' => 'Appointment must be scheduled in the future.',
                'code' => 'INVALID_SCHEDULE',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $durationMinutes = (int) ($payload['durationMinutes'] ?? 30);
        $scheduledEnd = $scheduledAt->modify('+' . $durationMinutes . ' minutes');

        if ($appointmentRepository->hasDoctorConflict($doctor, $scheduledAt, $scheduledEnd)) {
            return $this->json([
                'error' => 'Selected timeslot is no longer available.',
                'code' => 'TIMESLOT_UNAVAILABLE',
            ], JsonResponse::HTTP_CONFLICT);
        }

        $appointment = (new Appointment())
            ->setPatient($patient)
            ->setDoctor($doctor)
            ->setScheduledAt($scheduledAt)
            ->setDurationMinutes($durationMinutes)
            ->setReason(isset($payload['reason']) ? (string) $payload['reason'] : null);

        $appointmentRepository->save($appointment);

        return $this->json($this->serializeAppointment($appointment), JsonResponse::HTTP_CREATED);
    }

    #[Route('', name: 'api_appointments_list', methods: ['GET'])]
    public function list(AppointmentRepository $appointmentRepository): JsonResponse
    {
        $user = $this->requireUser();
        $appointments = $appointmentRepository->findForUser($user);

        return $this->json(array_map($this->serializeAppointment(...), $appointments));
    }

    #[Route('/{id}', name: 'api_appointments_show', methods: ['GET'])]
    public function show(Appointment $appointment): JsonResponse
    {
        $user = $this->requireUser();

        if (!$this->isParticipant($user, $appointment)) {
            return $this->json([
                'error' => 'Forbidden.',
                'code' => 'FORBIDDEN',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        return $this->json($this->serializeAppointment($appointment));
    }

    #[Route('/{id}/status', name: 'api_appointments_status', methods: ['PATCH'])]
    #[IsGranted('ROLE_DOCTOR')]
    public function updateStatus(
        Appointment $appointment,
        Request $request,
        AppointmentRepository $appointmentRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        $doctor = $this->requireUser();

        if ($appointment->getDoctor()->getId() !== $doctor->getId()) {
            return $this->json([
                'error' => 'Forbidden.',
                'code' => 'FORBIDDEN',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $violations = $validator->validate($payload, new Assert\Collection([
            'status' => [new Assert\Required([new Assert\Choice([
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_COMPLETED,
            ])])],
        ]));

        if (count($violations) > 0) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => $this->formatViolations($violations),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $newStatus = (string) $payload['status'];
        $current = $appointment->getStatus();
        $allowed = [
            Appointment::STATUS_PENDING => [Appointment::STATUS_CONFIRMED],
            Appointment::STATUS_CONFIRMED => [Appointment::STATUS_COMPLETED],
        ];

        if (!isset($allowed[$current]) || !in_array($newStatus, $allowed[$current], true)) {
            return $this->json([
                'error' => 'Invalid status transition.',
                'code' => 'INVALID_STATUS_TRANSITION',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $appointment->setStatus($newStatus);
        $appointmentRepository->save($appointment);

        return $this->json($this->serializeAppointment($appointment));
    }

    #[Route('/{id}/cancel', name: 'api_appointments_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function cancel(Appointment $appointment, AppointmentRepository $appointmentRepository): JsonResponse
    {
        $patient = $this->requireUser();

        if ($appointment->getPatient()->getId() !== $patient->getId()) {
            return $this->json([
                'error' => 'Forbidden.',
                'code' => 'FORBIDDEN',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (!in_array($appointment->getStatus(), [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED], true)) {
            return $this->json([
                'error' => 'Appointment cannot be cancelled in current state.',
                'code' => 'INVALID_STATUS_TRANSITION',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $appointment->cancel();
        $appointmentRepository->save($appointment);

        return $this->json($this->serializeAppointment($appointment));
    }

    private function requireUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException('Missing authentication.');
        }

        return $user;
    }

    private function isParticipant(User $user, Appointment $appointment): bool
    {
        return $appointment->getDoctor()->getId() === $user->getId()
            || $appointment->getPatient()->getId() === $user->getId();
    }

    private function serializeAppointment(Appointment $appointment): array
    {
        return [
            'id' => $appointment->getId(),
            'patient' => [
                'id' => $appointment->getPatient()->getId(),
                'email' => $appointment->getPatient()->getEmail(),
            ],
            'doctor' => [
                'id' => $appointment->getDoctor()->getId(),
                'email' => $appointment->getDoctor()->getEmail(),
            ],
            'scheduledAt' => $appointment->getScheduledAt()->format(DATE_ATOM),
            'durationMinutes' => $appointment->getDurationMinutes(),
            'status' => $appointment->getStatus(),
            'reason' => $appointment->getReason(),
            'createdAt' => $appointment->getCreatedAt()->format(DATE_ATOM),
            'cancelledAt' => $appointment->getCancelledAt()?->format(DATE_ATOM),
        ];
    }

    private function decodePayload(Request $request): array|JsonResponse
    {
        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json([
                'error' => 'Invalid JSON body.',
                'code' => 'INVALID_JSON',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!is_array($payload)) {
            return $this->json([
                'error' => 'Invalid JSON body.',
                'code' => 'INVALID_JSON',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $payload;
    }

    private function formatViolations(iterable $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = [
                'field' => trim((string) $violation->getPropertyPath(), '[]'),
                'message' => $violation->getMessage(),
            ];
        }

        return $errors;
    }
}
