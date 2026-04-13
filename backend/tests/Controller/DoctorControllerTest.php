<?php

declare(strict_types=1);

namespace App\Tests\Controller;

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
}
