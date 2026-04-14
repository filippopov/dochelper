<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\DoctorAvailabilityRepository;
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
        $appointments = $appointmentRepository->findForDoctorBetween($doctor, $startDate, $endDateExclusive);

        return $this->json([
            'doctor' => [
                'id' => $doctor->getId(),
                'email' => $doctor->getEmail(),
            ],
            'slotMinutes' => self::SLOT_MINUTES,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDateExclusive->modify('-1 day')->format('Y-m-d'),
            'days' => $this->buildDays($startDate, $endDateExclusive, $availabilities, $appointments),
        ]);
    }

    /**
     * @param list<\App\Entity\DoctorAvailability> $availabilities
     * @param list<Appointment> $appointments
     *
     * @return list<array{date: string, slots: list<array{startAt: string, endAt: string, status: string, appointment: ?array{id: int|null, patientEmail: string, status: string}}>}>
     */
    private function buildDays(\DateTimeImmutable $startDate, \DateTimeImmutable $endDateExclusive, array $availabilities, array $appointments): array
    {
        $appointmentsByDay = [];
        foreach ($appointments as $appointment) {
            $appointmentsByDay[$appointment->getScheduledAt()->format('Y-m-d')][] = $appointment;
        }

        $availabilityByDay = [];
        foreach ($availabilities as $availability) {
            $availabilityByDay[$availability->getDayOfWeek()][] = $availability;
        }

        $days = [];
        for ($day = $startDate; $day < $endDateExclusive; $day = $day->modify('+1 day')) {
            $dayAvailabilities = $availabilityByDay[(int) $day->format('N')] ?? [];
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
                        $appointmentsByDay[$day->format('Y-m-d')] ?? [],
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
                'date' => $day->format('Y-m-d'),
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
}
