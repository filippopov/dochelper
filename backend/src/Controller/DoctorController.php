<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\DoctorAvailability;
use App\Entity\DoctorAvailabilityOverride;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\DoctorAvailabilityRepository;
use App\Repository\DoctorAvailabilityOverrideRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/doctors')]
class DoctorController extends AbstractController
{
    private const SLOT_MINUTES = 30;

    #[Route('', name: 'api_doctors_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(UserRepository $userRepository): JsonResponse
    {
        $viewer = $this->requireUser();
        if (!$viewer->isPatient() && !$viewer->isAdmin()) {
            return $this->json([
                'error' => 'You are not allowed to list doctors.',
                'code' => 'DOCTOR_LIST_FORBIDDEN',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $doctors = $userRepository->findAllDoctors();

        return $this->json([
            'items' => array_map(
                static fn (User $doctor): array => [
                    'id' => $doctor->getId(),
                    'email' => $doctor->getEmail(),
                ],
                $doctors
            ),
        ]);
    }

    #[Route('/{id}/calendar', name: 'api_doctors_calendar', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function calendar(
        int $id,
        Request $request,
        UserRepository $userRepository,
        DoctorAvailabilityRepository $availabilityRepository,
        DoctorAvailabilityOverrideRepository $availabilityOverrideRepository,
        AppointmentRepository $appointmentRepository,
        AuthorizationCheckerInterface $authorizationChecker
    ): JsonResponse {
        $viewer = $this->requireUser();

        $doctor = $userRepository->findDoctorById($id);
        if (!$doctor instanceof User) {
            return $this->json([
                'error' => 'Doctor not found.',
                'code' => 'DOCTOR_NOT_FOUND',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($viewer->isDoctor() && $viewer->getId() !== $doctor->getId()) {
            return $this->json([
                'error' => 'Doctors can only view their own availability.',
                'code' => 'DOCTOR_CALENDAR_FORBIDDEN',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (!$viewer->isDoctor() && !$authorizationChecker->isGranted('ROLE_ADMIN') && !$authorizationChecker->isGranted('ROLE_PATIENT')) {
            return $this->json([
                'error' => 'You are not allowed to view doctor availability.',
                'code' => 'DOCTOR_CALENDAR_FORBIDDEN',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $range = $this->resolveDateRange($request);
        if ($range instanceof JsonResponse) {
            return $range;
        }

        [$startDate, $endDateExclusive] = $range;

        $availabilities = $availabilityRepository->findForDoctor($doctor);
    $availabilityOverrides = $availabilityOverrideRepository->findForDoctorBetween($doctor, $startDate, $endDateExclusive);
        $appointments = $appointmentRepository->findForDoctorBetween($doctor, $startDate, $endDateExclusive);

        return $this->json([
            'doctor' => [
                'id' => $doctor->getId(),
                'email' => $doctor->getEmail(),
            ],
            'slotMinutes' => self::SLOT_MINUTES,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDateExclusive->modify('-1 day')->format('Y-m-d'),
            'days' => $this->buildDays($startDate, $endDateExclusive, $availabilities, $availabilityOverrides, $appointments),
        ]);
    }

    #[Route('/{id}/availability', name: 'api_doctors_availability_day', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function availabilityDay(
        int $id,
        Request $request,
        UserRepository $userRepository,
        DoctorAvailabilityRepository $availabilityRepository,
        DoctorAvailabilityOverrideRepository $availabilityOverrideRepository,
        AuthorizationCheckerInterface $authorizationChecker
    ): JsonResponse {
        $doctor = $userRepository->findDoctorById($id);
        if (!$doctor instanceof User) {
            return $this->json([
                'error' => 'Doctor not found.',
                'code' => 'DOCTOR_NOT_FOUND',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $authorizationError = $this->assertCanManageDoctorAvailability($doctor, $authorizationChecker);
        if ($authorizationError instanceof JsonResponse) {
            return $authorizationError;
        }

        $dateRaw = (string) ($request->query->get('date') ?? '');
        try {
            $date = $dateRaw !== ''
                ? new \DateTimeImmutable($dateRaw . ' 00:00:00')
                : new \DateTimeImmutable('today 00:00:00');
        } catch (\Exception) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => [
                    ['field' => 'date', 'message' => 'Date must be a valid YYYY-MM-DD value.'],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $dayOfWeek = (int) $date->format('N');
        $overrideIntervals = $availabilityOverrideRepository->findForDoctorAndDate($doctor, $date);
        $weeklyIntervals = $availabilityRepository->findForDoctorAndDay($doctor, $dayOfWeek);
        $useOverride = count($overrideIntervals) > 0;
        $effectiveIntervals = $useOverride ? $overrideIntervals : $weeklyIntervals;

        return $this->json([
            'doctor' => [
                'id' => $doctor->getId(),
                'email' => $doctor->getEmail(),
            ],
            'date' => $date->format('Y-m-d'),
            'dayOfWeek' => $dayOfWeek,
            'mode' => 'date_override',
            'source' => $useOverride ? 'date_override' : 'weekly_fallback',
            'intervals' => $this->serializeIntervals($effectiveIntervals),
        ]);
    }

    #[Route('/{id}/availability', name: 'api_doctors_availability_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createAvailability(
        int $id,
        Request $request,
        UserRepository $userRepository,
        DoctorAvailabilityOverrideRepository $availabilityOverrideRepository,
        AuthorizationCheckerInterface $authorizationChecker
    ): JsonResponse {
        $doctor = $userRepository->findDoctorById($id);
        if (!$doctor instanceof User) {
            return $this->json([
                'error' => 'Doctor not found.',
                'code' => 'DOCTOR_NOT_FOUND',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $authorizationError = $this->assertCanManageDoctorAvailability($doctor, $authorizationChecker);
        if ($authorizationError instanceof JsonResponse) {
            return $authorizationError;
        }

        $payload = json_decode((string) $request->getContent(), true);
        $dateRaw = (string) ($payload['date'] ?? '');
        $startTimeRaw = (string) ($payload['startTime'] ?? '');
        $endTimeRaw = (string) ($payload['endTime'] ?? '');

        $parsed = $this->parseAvailabilityInput($dateRaw, $startTimeRaw, $endTimeRaw);
        if ($parsed instanceof JsonResponse) {
            return $parsed;
        }

        [$date, $startTime, $endTime] = $parsed;

        $existing = $availabilityOverrideRepository->findForDoctorAndDate($doctor, $date);
        if ($this->hasAvailabilityOverlap($existing, $startTime, $endTime)) {
            return $this->json([
                'error' => 'Availability overlaps with an existing interval for this day.',
                'code' => 'AVAILABILITY_OVERLAP',
            ], JsonResponse::HTTP_CONFLICT);
        }

        $availability = (new DoctorAvailabilityOverride())
            ->setDoctor($doctor)
            ->setDate($date)
            ->setStartTime($startTime)
            ->setEndTime($endTime);

        $availabilityOverrideRepository->save($availability);

        return $this->json([
            'message' => 'Availability interval created.',
            'availability' => [
                'id' => $availability->getId(),
                'date' => $date->format('Y-m-d'),
                'startTime' => $availability->getStartTime()->format('H:i'),
                'endTime' => $availability->getEndTime()->format('H:i'),
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}/availability/{availabilityId}', name: 'api_doctors_availability_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateAvailability(
        int $id,
        int $availabilityId,
        Request $request,
        UserRepository $userRepository,
        DoctorAvailabilityOverrideRepository $availabilityOverrideRepository,
        AuthorizationCheckerInterface $authorizationChecker
    ): JsonResponse {
        $doctor = $userRepository->findDoctorById($id);
        if (!$doctor instanceof User) {
            return $this->json([
                'error' => 'Doctor not found.',
                'code' => 'DOCTOR_NOT_FOUND',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $authorizationError = $this->assertCanManageDoctorAvailability($doctor, $authorizationChecker);
        if ($authorizationError instanceof JsonResponse) {
            return $authorizationError;
        }

        $availability = $availabilityOverrideRepository->findOwnedByDoctor($doctor, $availabilityId);
        if (!$availability instanceof DoctorAvailabilityOverride) {
            return $this->json([
                'error' => 'Availability interval not found.',
                'code' => 'AVAILABILITY_NOT_FOUND',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $startTimeRaw = (string) ($payload['startTime'] ?? '');
        $endTimeRaw = (string) ($payload['endTime'] ?? '');

        $parsedTimes = $this->parseAvailabilityTimes($startTimeRaw, $endTimeRaw);
        if ($parsedTimes instanceof JsonResponse) {
            return $parsedTimes;
        }

        [$startTime, $endTime] = $parsedTimes;
        $existing = $availabilityOverrideRepository->findForDoctorAndDate($doctor, $availability->getDate());

        if ($this->hasAvailabilityOverlap($existing, $startTime, $endTime, $availability->getId())) {
            return $this->json([
                'error' => 'Availability overlaps with an existing interval for this day.',
                'code' => 'AVAILABILITY_OVERLAP',
            ], JsonResponse::HTTP_CONFLICT);
        }

        $availability
            ->setStartTime($startTime)
            ->setEndTime($endTime);

        $availabilityOverrideRepository->save($availability);

        return $this->json([
            'message' => 'Availability interval updated.',
            'availability' => [
                'id' => $availability->getId(),
                'date' => $availability->getDate()->format('Y-m-d'),
                'startTime' => $availability->getStartTime()->format('H:i'),
                'endTime' => $availability->getEndTime()->format('H:i'),
            ],
        ]);
    }

    #[Route('/{id}/availability/{availabilityId}', name: 'api_doctors_availability_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteAvailability(
        int $id,
        int $availabilityId,
        UserRepository $userRepository,
        DoctorAvailabilityOverrideRepository $availabilityOverrideRepository,
        AuthorizationCheckerInterface $authorizationChecker
    ): JsonResponse {
        $doctor = $userRepository->findDoctorById($id);
        if (!$doctor instanceof User) {
            return $this->json([
                'error' => 'Doctor not found.',
                'code' => 'DOCTOR_NOT_FOUND',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $authorizationError = $this->assertCanManageDoctorAvailability($doctor, $authorizationChecker);
        if ($authorizationError instanceof JsonResponse) {
            return $authorizationError;
        }

        $availability = $availabilityOverrideRepository->findOwnedByDoctor($doctor, $availabilityId);
        if (!$availability instanceof DoctorAvailabilityOverride) {
            return $this->json([
                'error' => 'Availability interval not found.',
                'code' => 'AVAILABILITY_NOT_FOUND',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $availabilityOverrideRepository->remove($availability);

        return $this->json([
            'message' => 'Availability interval deleted.',
        ]);
    }

    /**
     * @param list<\App\Entity\DoctorAvailability> $availabilities
     * @param list<DoctorAvailabilityOverride> $availabilityOverrides
     * @param list<Appointment> $appointments
     *
     * @return list<array{date: string, slots: list<array{startAt: string, endAt: string, status: string, appointment: ?array{id: int|null, patientEmail: string, status: string}}>}>
     */
    private function buildDays(\DateTimeImmutable $startDate, \DateTimeImmutable $endDateExclusive, array $availabilities, array $availabilityOverrides, array $appointments): array
    {
        $appointmentsByDay = [];
        foreach ($appointments as $appointment) {
            $appointmentsByDay[$appointment->getScheduledAt()->format('Y-m-d')][] = $appointment;
        }

        $availabilityByDay = [];
        foreach ($availabilities as $availability) {
            $availabilityByDay[$availability->getDayOfWeek()][] = $availability;
        }

        $availabilityOverrideByDate = [];
        foreach ($availabilityOverrides as $override) {
            $availabilityOverrideByDate[$override->getDate()->format('Y-m-d')][] = $override;
        }

        $days = [];
        for ($day = $startDate; $day < $endDateExclusive; $day = $day->modify('+1 day')) {
            $dateKey = $day->format('Y-m-d');
            $dayAvailabilities = $availabilityOverrideByDate[$dateKey] ?? $availabilityByDay[(int) $day->format('N')] ?? [];
            $slots = [];

            foreach ($dayAvailabilities as $availability) {
                $slotStart = $this->combineDayWithTime($day, $availability->getStartTime());
                $windowEnd = $this->combineDayWithTime($day, $availability->getEndTime());

                while ($slotStart < $windowEnd) {
                    $slotEnd = $slotStart->modify('+' . self::SLOT_MINUTES . ' minutes');

                    if ($slotEnd > $windowEnd) {
                        break;
                    }

                    $matchedAppointment = $this->findOverlappingAppointment(
                        $appointmentsByDay[$dateKey] ?? [],
                        $slotStart,
                        $slotEnd
                    );

                    $slots[] = [
                        'startAt' => $slotStart->format(DATE_ATOM),
                        'endAt' => $slotEnd->format(DATE_ATOM),
                        'status' => $matchedAppointment instanceof Appointment ? 'booked' : 'available',
                        'appointment' => $matchedAppointment instanceof Appointment
                            ? [
                                'id' => $matchedAppointment->getId(),
                                'patientEmail' => $matchedAppointment->getPatient()->getEmail(),
                                'status' => $matchedAppointment->getStatus(),
                            ]
                            : null,
                    ];

                    $slotStart = $slotEnd;
                }
            }

            $days[] = [
                'date' => $dateKey,
                'slots' => $slots,
            ];
        }

        return $days;
    }

    /**
     * @param list<Appointment> $appointments
     */
    private function findOverlappingAppointment(array $appointments, \DateTimeImmutable $slotStart, \DateTimeImmutable $slotEnd): ?Appointment
    {
        foreach ($appointments as $appointment) {
            $appointmentStart = $appointment->getScheduledAt();
            $appointmentEnd = $appointmentStart->modify('+' . $appointment->getDurationMinutes() . ' minutes');

            if ($appointmentStart < $slotEnd && $appointmentEnd > $slotStart) {
                return $appointment;
            }
        }

        return null;
    }

    private function combineDayWithTime(\DateTimeImmutable $day, \DateTimeImmutable $time): \DateTimeImmutable
    {
        return $day->setTime(
            (int) $time->format('H'),
            (int) $time->format('i'),
            0
        );
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}|JsonResponse
     */
    private function resolveDateRange(Request $request): array|JsonResponse
    {
        $startRaw = (string) ($request->query->get('startDate') ?? '');
        $endRaw = (string) ($request->query->get('endDate') ?? '');

        try {
            $start = $startRaw !== ''
                ? new \DateTimeImmutable($startRaw . ' 00:00:00')
                : new \DateTimeImmutable('today 00:00:00');
            $endInclusive = $endRaw !== ''
                ? new \DateTimeImmutable($endRaw . ' 00:00:00')
                : $start->modify('+6 days');
        } catch (\Exception) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => [
                    ['field' => 'startDate/endDate', 'message' => 'Dates must be valid YYYY-MM-DD values.'],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($endInclusive < $start) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => [
                    ['field' => 'endDate', 'message' => 'endDate must be greater than or equal to startDate.'],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($start->diff($endInclusive)->days > 31) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => [
                    ['field' => 'endDate', 'message' => 'Date range cannot exceed 31 days.'],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return [$start, $endInclusive->modify('+1 day')];
    }

    private function requireUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException('Missing authentication.');
        }

        return $user;
    }

    private function assertCanManageDoctorAvailability(User $doctor, AuthorizationCheckerInterface $authorizationChecker): ?JsonResponse
    {
        $viewer = $this->requireUser();

        if ($viewer->isDoctor() && $viewer->getId() !== $doctor->getId()) {
            return $this->json([
                'error' => 'Doctors can only manage their own availability.',
                'code' => 'DOCTOR_AVAILABILITY_FORBIDDEN',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (!$viewer->isDoctor() && !$authorizationChecker->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'error' => 'You are not allowed to manage doctor availability.',
                'code' => 'DOCTOR_AVAILABILITY_FORBIDDEN',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        return null;
    }

    /**
    * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable, 2: \DateTimeImmutable}|JsonResponse
     */
    private function parseAvailabilityInput(string $dateRaw, string $startTimeRaw, string $endTimeRaw): array|JsonResponse
    {
        if ($dateRaw === '') {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => [
                    ['field' => 'date', 'message' => 'date is required.'],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $date = new \DateTimeImmutable($dateRaw . ' 00:00:00');
        } catch (\Exception) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => [
                    ['field' => 'date', 'message' => 'Date must be a valid YYYY-MM-DD value.'],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $times = $this->parseAvailabilityTimes($startTimeRaw, $endTimeRaw);
        if ($times instanceof JsonResponse) {
            return $times;
        }

        [$startTime, $endTime] = $times;

        return [$date, $startTime, $endTime];
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}|JsonResponse
     */
    private function parseAvailabilityTimes(string $startTimeRaw, string $endTimeRaw): array|JsonResponse
    {
        if ($startTimeRaw === '' || $endTimeRaw === '') {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => [
                    ['field' => 'startTime/endTime', 'message' => 'startTime and endTime are required.'],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $startTimeRaw) || !preg_match('/^\d{2}:\d{2}$/', $endTimeRaw)) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => [
                    ['field' => 'startTime/endTime', 'message' => 'Times must use HH:MM format.'],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $startTime = new \DateTimeImmutable($startTimeRaw . ':00');
            $endTime = new \DateTimeImmutable($endTimeRaw . ':00');
        } catch (\Exception) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => [
                    ['field' => 'startTime/endTime', 'message' => 'Times must be valid values.'],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($endTime <= $startTime) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => [
                    ['field' => 'endTime', 'message' => 'endTime must be greater than startTime.'],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ((int) $startTime->format('i') % self::SLOT_MINUTES !== 0 || (int) $endTime->format('i') % self::SLOT_MINUTES !== 0) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => [
                    ['field' => 'startTime/endTime', 'message' => 'Times must align to 30-minute slots.'],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return [$startTime, $endTime];
    }

    /**
    * @param list<DoctorAvailability>|list<DoctorAvailabilityOverride> $availabilities
     */
    private function hasAvailabilityOverlap(array $availabilities, \DateTimeImmutable $startTime, \DateTimeImmutable $endTime, ?int $ignoreId = null): bool
    {
        foreach ($availabilities as $availability) {
            if ($ignoreId !== null && $availability->getId() === $ignoreId) {
                continue;
            }

            $existingStart = $availability->getStartTime();
            $existingEnd = $availability->getEndTime();
            if ($startTime < $existingEnd && $endTime > $existingStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<DoctorAvailability>|list<DoctorAvailabilityOverride> $intervals
     *
     * @return list<array{id: int|null, startTime: string, endTime: string}>
     */
    private function serializeIntervals(array $intervals): array
    {
        return array_map(
            static fn (DoctorAvailability|DoctorAvailabilityOverride $interval): array => [
                'id' => $interval->getId(),
                'startTime' => $interval->getStartTime()->format('H:i'),
                'endTime' => $interval->getEndTime()->format('H:i'),
            ],
            $intervals
        );
    }
}
