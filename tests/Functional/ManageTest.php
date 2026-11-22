<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Booking;
use App\Entity\ClassSession;
use App\Entity\Studio;
use App\Entity\User;

final class ManageTest extends WebTestCaseHelper
{
    public function testMembersCannotAccessTheBackOffice(): void
    {
        $this->client->loginUser($this->data->user('member@example.test'));

        $this->client->request('GET', '/gestion');

        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnerCannotManageAnotherOwnersStudio(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]));
        $this->client->loginUser($this->data->user('other@example.test', [User::ROLE_OWNER]));

        $this->client->request('GET', '/gestion/studios/'.$studio->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/gestion/studios/'.$studio->getId().'/modifier');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanManageAnyStudio(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]), 'Atelier Soleil');
        $this->client->loginUser($this->data->user('admin@example.test', [User::ROLE_ADMIN]));

        $this->client->request('GET', '/gestion/studios/'.$studio->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Atelier Soleil');
    }

    public function testOwnerCreatesAStudioThenAnActivityThenRecurringSessions(): void
    {
        $owner = $this->data->user('owner@example.test', [User::ROLE_OWNER]);
        $discipline = $this->data->discipline('Pilates');
        $this->client->loginUser($owner);

        $this->client->request('GET', '/gestion/studios/nouveau');
        $this->client->submitForm('Enregistrer', [
            'studio[name]' => 'Atelier Neuf',
            'studio[disciplines]' => [(string) $discipline->getId()],
            'studio[address]' => '3 place du Marché',
            'studio[postalCode]' => '44000',
            'studio[city]' => 'Nantes',
            'studio[cancellationDeadlineHours]' => '4',
            'studio[published]' => true,
        ]);
        self::assertResponseRedirects();

        $studio = $this->entityManager()->getRepository(Studio::class)->findOneBy(['name' => 'Atelier Neuf']);
        self::assertNotNull($studio);
        self::assertSame('atelier-neuf-nantes', $studio->getSlug());
        self::assertSame($owner->getId(), $studio->getOwner()->getId());

        $this->client->request('GET', '/gestion/studios/'.$studio->getId().'/cours/nouveau');
        $this->client->submitForm('Enregistrer', [
            'activity[name]' => 'Pilates reformer',
            'activity[discipline]' => (string) $discipline->getId(),
            'activity[level]' => 'beginner',
            'activity[durationMinutes]' => '50',
            'activity[defaultCapacity]' => '6',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        $this->client->submitForm('Enregistrer', [
            'class_session[startsAt]' => '2026-11-25T19:00',
            'class_session[capacity]' => '6',
            'class_session[instructorName]' => 'Julie',
            'class_session[weeklyRepetitions]' => '2',
        ]);
        self::assertResponseRedirects('/gestion/studios/'.$studio->getId());

        $sessions = $this->entityManager()->getRepository(ClassSession::class)->findBy([], ['startsAt' => 'ASC']);
        self::assertCount(3, $sessions);
        self::assertSame('2026-11-25 19:50', $sessions[0]->getEndsAt()->format('Y-m-d H:i'));
        self::assertSame('2026-12-09 19:00', $sessions[2]->getStartsAt()->format('Y-m-d H:i'));
    }

    public function testSessionInThePastIsRejected(): void
    {
        $owner = $this->data->user('owner@example.test', [User::ROLE_OWNER]);
        $studio = $this->data->studio($owner);
        $activity = $this->data->session($studio, new \DateTimeImmutable('2026-11-30 10:00'))->getActivity();
        $this->client->loginUser($owner);

        $this->client->request('GET', '/gestion/cours/'.$activity->getId().'/seances/nouvelle');
        $this->client->submitForm('Enregistrer', [
            'class_session[startsAt]' => '2026-11-01T10:00',
            'class_session[capacity]' => '10',
            'class_session[weeklyRepetitions]' => '0',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'futur');
    }

    public function testCapacityCannotDropBelowConfirmedBookings(): void
    {
        $owner = $this->data->user('owner@example.test', [User::ROLE_OWNER]);
        $session = $this->data->session($this->data->studio($owner), new \DateTimeImmutable('2026-11-23 18:00'), capacity: 5);
        foreach (['a', 'b', 'c'] as $name) {
            $this->entityManager()->persist(new Booking($this->data->user($name.'@example.test'), $session, new \DateTimeImmutable()));
        }
        $this->entityManager()->flush();
        $this->client->loginUser($owner);

        $this->client->request('GET', '/gestion/seances/'.$session->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['class_session[capacity]' => '2']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'déjà réservé');
    }

    public function testOwnerChecksInAnAttendee(): void
    {
        $owner = $this->data->user('owner@example.test', [User::ROLE_OWNER]);
        $session = $this->data->session($this->data->studio($owner), new \DateTimeImmutable('2026-11-23 18:00'));
        $booking = new Booking($this->data->user('member@example.test'), $session, new \DateTimeImmutable());
        $this->entityManager()->persist($booking);
        $this->entityManager()->flush();
        $this->client->loginUser($owner);

        $this->client->request('GET', '/gestion/seances/'.$session->getId());
        $this->client->submitForm('Pointer');

        self::assertResponseRedirects('/gestion/seances/'.$session->getId());
        $booking = $this->entityManager()->getRepository(Booking::class)->find($booking->getId());
        self::assertNotNull($booking?->getCheckedInAt());
    }
}
