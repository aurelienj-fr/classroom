<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Booking;
use App\Entity\Studio;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Enum\OwnerRequestStatus;
use Symfony\Component\Mime\Email;

/**
 * E-mails are queued on the "async" transport (in-memory in tests): assertQueuedEmailCount().
 */
final class NotificationTest extends WebTestCaseHelper
{
    public function testBookingAndCancellationAreConfirmedByEmailInTheMemberLanguage(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]));
        $session = $this->data->session($studio, new \DateTimeImmutable('2026-11-23 18:00'), name: 'Vinyasa');
        $member = $this->data->user('member@example.test');
        $member->setLocale('en');
        $this->entityManager()->flush();
        $this->client->loginUser($member);

        $this->client->request('GET', '/fr/seances/'.$session->getId());
        $this->client->submitForm('Réserver ma place');

        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', 'member@example.test');
        self::assertSame('Booking confirmed: Vinyasa', $email->getSubject(), 'Sent in the member language, not the page language.');
        self::assertEmailHtmlBodyContains($email, 'Monday, November 23, 2026 at 18:00');
        self::assertEmailTextBodyContains($email, 'Your spot is booked');

        $this->client->request('GET', '/fr/compte/reservations');
        $this->client->submitForm('Annuler');

        self::assertQueuedEmailCount(1);
        self::assertSame('Booking cancelled: Vinyasa', $this->lastEmail()->getSubject());
    }

    public function testMembersAreNotifiedWhenTheStudioCancelsASession(): void
    {
        $owner = $this->data->user('owner@example.test', [User::ROLE_OWNER]);
        $session = $this->data->session($this->data->studio($owner, 'Atelier Soleil'), new \DateTimeImmutable('2026-11-23 18:00'), name: 'Yin');
        foreach (['a', 'b'] as $name) {
            $this->entityManager()->persist(new Booking($this->data->user($name.'@example.test'), $session, new \DateTimeImmutable()));
        }
        $this->entityManager()->flush();
        $this->client->loginUser($owner);

        $this->client->request('GET', '/fr/gestion/seances/'.$session->getId());
        $this->client->submitForm('Annuler la séance');

        self::assertQueuedEmailCount(2);
        $recipients = array_map(
            static fn ($message): string => $message instanceof Email ? $message->getTo()[0]->getAddress() : '',
            self::getMailerMessages(),
        );
        self::assertEqualsCanonicalizing(['a@example.test', 'b@example.test'], $recipients);
        self::assertSame('Séance annulée : Yin', $this->lastEmail()->getSubject());
        self::assertEmailHtmlBodyContains($this->lastEmail(), 'Atelier Soleil a dû annuler');
    }

    public function testAdministratorsAreNotifiedOfOwnerRequestsAndApplicantsOfTheDecision(): void
    {
        $admin = $this->data->user('admin@example.test', [User::ROLE_ADMIN]);
        $this->data->user('second-admin@example.test', [User::ROLE_ADMIN]);
        $member = $this->data->user('member@example.test');
        $this->client->loginUser($member);

        $this->client->request('GET', '/fr/compte/reservations');
        $this->client->submitForm('Demander un compte gérant');

        self::assertQueuedEmailCount(2, message: 'One e-mail per administrator.');
        self::assertSame('Nouvelle demande de compte gérant : Test User', $this->lastEmail()->getSubject());

        $this->client->loginUser($admin);
        $this->client->request('GET', '/fr/admin/gerants');
        $this->client->submitForm('Valider');

        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', 'member@example.test');
        self::assertSame('Votre compte gérant est validé', $email->getSubject());
    }

    public function testLoginRemembersTheLanguage(): void
    {
        $member = $this->data->user('member@example.test');

        $this->client->request('GET', '/es/acceso');
        $this->client->submitForm('Iniciar sesión', ['_username' => 'member@example.test', '_password' => 'correct-horse-battery']);

        $reloaded = $this->entityManager()->getRepository(User::class)->find($member->getId());
        self::assertSame('es', $reloaded?->getLocale());
    }

    public function testOwnerGoesBackToAMember(): void
    {
        $owner = $this->data->user('owner@example.test');
        $owner->requestOwnerRole(new \DateTimeImmutable('2026-11-01'));
        $owner->reviewOwnerRequest(true, new \DateTimeImmutable('2026-11-02'));
        $studio = $this->data->studio($owner, 'Atelier Soleil');
        $upcoming = $this->data->session($studio, new \DateTimeImmutable('2026-11-23 18:00'));
        $past = $this->data->session($studio, new \DateTimeImmutable('2026-11-20 18:00'));
        $booking = new Booking($this->data->user('member@example.test'), $upcoming, new \DateTimeImmutable());
        $this->entityManager()->persist($booking);
        $this->entityManager()->flush();
        $this->client->loginUser($owner);

        $this->client->request('GET', '/fr/gestion');
        $this->client->submitForm('Ne plus être gérant');

        self::assertResponseRedirects('/fr/compte/reservations');
        // The member of the cancelled session and the former owner are notified.
        self::assertQueuedEmailCount(2);

        $em = $this->entityManager();
        $owner = $em->getRepository(User::class)->find($owner->getId());
        self::assertNotNull($owner);
        self::assertNotContains(User::ROLE_OWNER, $owner->getRoles());
        self::assertNull($owner->getOwnerRequestStatus(), 'A new request is possible.');
        self::assertFalse($em->getRepository(Studio::class)->find($studio->getId())?->isPublished(), 'Studio kept as a draft.');
        self::assertSame(BookingStatus::CancelledByStudio, $em->getRepository(Booking::class)->find($booking->getId())?->getStatus());
        self::assertFalse($em->getRepository(\App\Entity\ClassSession::class)->find($past->getId())?->isCancelled(), 'Past sessions are history.');

        // Still logged in, as a member.
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-success', 'simple usager');
        $this->client->request('GET', '/fr/gestion');
        self::assertResponseStatusCodeSame(403);

        // ...and can ask again.
        $this->client->request('GET', '/fr/compte/reservations');
        $this->client->submitForm('Demander un compte gérant');
        self::assertSame(OwnerRequestStatus::Pending, $this->entityManager()->getRepository(User::class)->find($owner->getId())?->getOwnerRequestStatus());
    }

    public function testAdministratorsAreNotOfferedToRelinquish(): void
    {
        // Administrators manage through ROLE_ADMIN; the controller also refuses the action for them.
        $this->client->loginUser($this->data->user('admin@example.test', [User::ROLE_ADMIN]));

        $this->client->request('GET', '/fr/gestion');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action$="/ne-plus-etre-gerant"]');
    }

    public function testMembersCannotRelinquishAnOwnerRoleTheyDoNotHave(): void
    {
        $this->client->loginUser($this->data->user('member@example.test'));

        $this->client->request('POST', '/fr/compte/ne-plus-etre-gerant', ['_token' => 'x']);

        self::assertResponseStatusCodeSame(403);
    }

    private function lastEmail(): Email
    {
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }
}
