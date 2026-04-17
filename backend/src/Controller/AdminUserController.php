<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    #[Route('/users', name: 'api_admin_users_list', methods: ['GET'])]
    public function listUsers(Request $request, UserRepository $userRepository): JsonResponse
    {
        $this->requireUser();

        $query = trim((string) $request->query->get('q', ''));
        $users = $userRepository->searchForAdmin($query);

        return $this->json([
            'items' => array_map($this->serializeUser(...), $users),
        ]);
    }

    #[Route('/users/{id}/role', name: 'api_admin_users_update_role', methods: ['PATCH'])]
    public function updateRole(
        User $targetUser,
        Request $request,
        UserRepository $userRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        $actor = $this->requireUser();

        if ($actor->getId() === $targetUser->getId()) {
            return $this->json([
                'error' => 'You cannot change your own role.',
                'code' => 'SELF_ROLE_CHANGE_NOT_ALLOWED',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $violations = $validator->validate($payload, new Assert\Collection([
            'roleType' => [new Assert\Required([new Assert\Choice([
                User::ROLE_TYPE_PATIENT,
                User::ROLE_TYPE_DOCTOR,
                User::ROLE_TYPE_ADMIN,
            ])])],
        ]));

        if (count($violations) > 0) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => $this->formatViolations($violations),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $targetUser->setRoleType((string) $payload['roleType']);
        $userRepository->save($targetUser);

        return $this->json($this->serializeUser($targetUser));
    }

    private function requireUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException('Missing authentication.');
        }

        return $user;
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roleType' => $user->getRoleType(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
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
