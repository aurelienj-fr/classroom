<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Booking;
use App\Entity\User;
use App\Enum\BookingStatus;

final class BookingFlowTest extends WebTestCaseHelper
{
    public function testAnonymousVisitorIsAskedToLogIn(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]));
        $session = $this->data->session($studio, new \DateTimeImmutable('2026-11-23 18:00'));

        $this->client->request('GET', '/seances/'.$session->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ms-card a.btn-accent', 'Se connecter pour réserver');
    }

    public function testMemberBooksThenCancelsASession(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]));
        $session = $this->data->session($studio, new \DateTimeImmutable('2026-11-23 18:00'));
        $member = $this->data->user('member@example.test');
        $this->client->loginUser($member);

        $this->client->request('GET', '/seances/'.$session->getId());
        $this->client->submitForm('Réserver ma place');

        self::assertResponseRedirects('/seances/'.$session->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Votre place est réservée');

        $booking = $this->entityManager()->getRepository(Booking::class)->findOneBy(['member' => $member, 'session' => $session]);
        self::assertNotNull($booking);
        self::assertTrue($booking->isConfirmed());

        $this->client->request('GET', '/compte/reservations');
        self::assertAnySelectorTextContains('.ms-session', 'Hatha yoga');
        $this->client->submitForm('Annuler');

        self::assertResponseRedirects('/compte/reservations');
        // The kernel reboots between requests: reload the booking from the database.
        $booking = $this->entityManager()->getRepository(Booking::class)->find($booking->getId());
        self::assertSame(BookingStatus::Cancelled, $booking?->getStatus());
    }

    public function testBookingWithInvalidCsrfTokenIsRejected(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]));
        $session = $this->data->session($studio, new \DateTimeImmutable('2026-11-23 18:00'));
        $this->client->loginUser($this->data->user('member@example.test'));

        $this->client->request('POST', '/seances/'.$session->getId().'/reserver', ['_token' => 'invalid']);

        // Symfony reports an invalid CSRF token as an authentication failure (redirect to the login form).
        self::assertResponseRedirects('/connexion');
        self::assertSame(0, $this->entityManager()->getRepository(Booking::class)->count([]));
    }

    public function testFullSessionCannotBeBooked(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]));
        $session = $this->data->session($studio, new \DateTimeImmutable('2026-11-23 18:00'), capacity: 1);
        $this->entityManager()->persist(new Booking($this->data->user('first@example.test'), $session, new \DateTimeImmutable()));
        $this->entityManager()->flush();
        $this->client->loginUser($this->data->user('second@example.test'));

        $this->client->request('GET', '/seances/'.$session->getId());

        self::assertSelectorTextContains('.alert-danger', 'Séance complète');
        self::assertSelectorNotExists('form[action$="/reserver"]');
    }

    public function testMemberCannotCancelSomeoneElsesBooking(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]));
        $session = $this->data->session($studio, new \DateTimeImmutable('2026-11-23 18:00'));
        $booking = new Booking($this->data->user('member@example.test'), $session, new \DateTimeImmutable());
        $this->entityManager()->persist($booking);
        $this->entityManager()->flush();

        $this->client->loginUser($this->data->user('intruder@example.test'));
        $this->client->request('POST', '/compte/reservations/'.$booking->getId().'/annuler', ['_token' => 'whatever']);

        self::assertResponseStatusCodeSame(403);
    }
}
