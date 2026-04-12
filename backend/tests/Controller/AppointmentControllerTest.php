<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
}
