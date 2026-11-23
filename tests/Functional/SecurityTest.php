<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Kernel;

final class SecurityTest extends WebTestCaseHelper
{
    public function testSecurityHeadersAreSent(): void
    {
        $this->client->request('GET', '/fr');

        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('Content-Security-Policy', "frame-ancestors 'none'");
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function testRegistrationIsRateLimited(): void
    {
        // Keep one kernel (and its in-memory limiter storage) across requests.
        $this->client->disableReboot();

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->client->request('GET', '/fr/inscription');
            $this->client->submitForm('Créer mon compte', ['registration_form[email]' => 'not-an-email']);
            self::assertResponseStatusCodeSame(422, 'Attempt '.$attempt.' is only rejected by validation.');
        }

        $this->client->request('GET', '/fr/inscription');
        $this->client->submitForm('Créer mon compte', ['registration_form[email]' => 'not-an-email']);

        self::assertResponseStatusCodeSame(429);
        self::assertSelectorTextContains('.alert-danger, .invalid-feedback', 'Trop de tentatives');
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $this->data->user('taken@example.test');

        $this->client->request('GET', '/fr/inscription');
        $this->client->submitForm('Créer mon compte', [
            'registration_form[firstName]' => 'Zoé',
            'registration_form[lastName]' => 'Lambert',
            'registration_form[email]' => 'TAKEN@example.test',
            'registration_form[plainPassword][first]' => 'un-mot-de-passe-solide',
            'registration_form[plainPassword][second]' => 'un-mot-de-passe-solide',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Un compte existe déjà');
    }

    public function testApplicationTimezoneIsPinned(): void
    {
        self::assertSame(Kernel::TIMEZONE, date_default_timezone_get());
    }
}
