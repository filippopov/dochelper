<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class AuthController extends AbstractController
{
    private const ACCESS_TTL_SECONDS = 3600;

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $violations = $validator->validate($payload, new Assert\Collection([
            'email' => [new Assert\Required([new Assert\Email(), new Assert\NotBlank()])],
            'password' => [new Assert\Required([new Assert\Length(min: 8), new Assert\NotBlank()])],
            'roleType' => [new Assert\Optional([new Assert\Choice([User::ROLE_TYPE_PATIENT, User::ROLE_TYPE_DOCTOR, User::ROLE_TYPE_ADMIN])])],
        ]));

        if (count($violations) > 0) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => $this->formatViolations($violations),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $email = mb_strtolower((string) $payload['email']);
        $password = (string) $payload['password'];
        $roleType = (string) ($payload['roleType'] ?? User::ROLE_TYPE_PATIENT);

        if ($roleType === User::ROLE_TYPE_DOCTOR) {
            return $this->json([
                'error' => 'Public registration is limited to patient accounts.',
                'code' => 'DOCTOR_REGISTRATION_NOT_ALLOWED',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($roleType === User::ROLE_TYPE_ADMIN) {
            return $this->json([
                'error' => 'Public registration is limited to patient accounts.',
                'code' => 'ADMIN_REGISTRATION_NOT_ALLOWED',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($userRepository->findOneBy(['email' => $email]) !== null) {
            return $this->json([
                'error' => 'Email already exists.',
                'code' => 'DUPLICATE_EMAIL',
            ], JsonResponse::HTTP_CONFLICT);
        }

        $user = (new User())
            ->setEmail($email)
            ->setPassword('')
            ->setRoleType(User::ROLE_TYPE_PATIENT);

        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $userRepository->save($user);

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roleType' => $user->getRoleType(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $tokenManager,
        RefreshTokenService $refreshTokenService
    ): JsonResponse {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $email = mb_strtolower((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json([
                'error' => 'Email and password are required.',
                'code' => 'VALIDATION_ERROR',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if ($user === null || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'error' => 'Invalid email or password.',
                'code' => 'INVALID_CREDENTIALS',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $token = $tokenManager->create($user);
        $refresh = $refreshTokenService->issueForUser($user);

        return $this->json([
            'token' => $token,
            'expiresIn' => self::ACCESS_TTL_SECONDS,
            'refreshToken' => $refresh['plainToken'],
            'refreshExpiresAt' => $refresh['expiresAt']->format(DATE_ATOM),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roleType' => $user->getRoleType(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    #[Route('/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refreshToken(
        Request $request,
        RefreshTokenService $refreshTokenService,
        JWTTokenManagerInterface $tokenManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $violations = $validator->validate($payload, new Assert\Collection([
            'refreshToken' => [new Assert\Required([new Assert\NotBlank()])],
        ]));

        if (count($violations) > 0) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => $this->formatViolations($violations),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rotated = $refreshTokenService->consumeAndRotate((string) $payload['refreshToken']);

        if (!is_array($rotated) || !($rotated['user'] ?? null) instanceof User) {
            return $this->json([
                'error' => 'Invalid or expired refresh token.',
                'code' => 'INVALID_REFRESH_TOKEN',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        /** @var User $user */
        $user = $rotated['user'];

        return $this->json([
            'token' => $tokenManager->create($user),
            'expiresIn' => self::ACCESS_TTL_SECONDS,
            'refreshToken' => (string) $rotated['refreshToken'],
            'refreshExpiresAt' => $rotated['refreshExpiresAt']->format(DATE_ATOM),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roleType' => $user->getRoleType(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function logout(
        Request $request,
        RefreshTokenService $refreshTokenService,
        ValidatorInterface $validator
    ): JsonResponse {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $violations = $validator->validate($payload, new Assert\Collection([
            'refreshToken' => [new Assert\Required([new Assert\NotBlank()])],
        ]));

        if (count($violations) > 0) {
            return $this->json([
                'error' => 'Validation failed.',
                'code' => 'VALIDATION_ERROR',
                'details' => $this->formatViolations($violations),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $refreshTokenService->revokeByPlainToken((string) $payload['refreshToken']);

        return $this->json(['status' => 'ok']);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException('Missing authentication.');
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roleType' => $user->getRoleType(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
        ]);
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
