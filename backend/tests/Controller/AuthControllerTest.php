<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    public function testLoginRequiresPayload(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertResponseStatusCodeSame(422);
    }

    public function testRegisterRequiresValidEmailAndPassword(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'not-an-email',
            'password' => '123',
            'firstName' => 'Test',
            'lastName' => 'User',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testDoctorRegistrationIsForbidden(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'doctor-test@example.com',
            'password' => 'secret123',
            'firstName' => 'Doc',
            'lastName' => 'Test',
            'roleType' => 'doctor',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminRegistrationIsForbidden(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'admin-test@example.com',
            'password' => 'secret123',
            'firstName' => 'Admin',
            'lastName' => 'Test',
            'roleType' => 'admin',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(403);
    }

    public function testRefreshRequiresRefreshToken(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/token/refresh', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertResponseStatusCodeSame(422);
    }
}
