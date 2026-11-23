<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\OwnerRequestStatus;

final class OwnerRequestTest extends WebTestCaseHelper
{
    public function testMemberRequestsAnOwnerAccountFromTheAccountPage(): void
    {
        $member = $this->data->user('member@example.test');
        $this->client->loginUser($member);

        $this->client->request('GET', '/fr/compte/reservations');
        $this->client->submitForm('Demander un compte gérant');

        self::assertResponseRedirects('/fr/compte/reservations');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-info', 'Un administrateur va la vérifier');
        self::assertSelectorNotExists('form[action$="/devenir-gerant"]', 'No second request while one is pending.');
        self::assertSame(OwnerRequestStatus::Pending, $this->reload($member)->getOwnerRequestStatus());
    }

    public function testAdminApprovesARequest(): void
    {
        $applicant = $this->pendingApplicant();
        $this->client->loginUser($this->data->user('admin@example.test', [User::ROLE_ADMIN]));

        $this->client->request('GET', '/fr/admin/gerants');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('nav .badge', '1');
        self::assertAnySelectorTextContains('td', 'applicant@example.test');

        $this->client->submitForm('Valider');
        self::assertResponseRedirects('/fr/admin/gerants');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'est validé');

        $applicant = $this->reload($applicant);
        self::assertSame(OwnerRequestStatus::Approved, $applicant->getOwnerRequestStatus());
        self::assertContains(User::ROLE_OWNER, $applicant->getRoles());
        self::assertNotNull($applicant->getOwnerReviewedAt());

        // The approved owner can now open the back-office.
        $this->client->loginUser($applicant);
        $this->client->request('GET', '/fr/gestion');
        self::assertResponseIsSuccessful();
    }

    public function testAdminRejectsARequestAndTheMemberCanAskAgain(): void
    {
        $applicant = $this->pendingApplicant();
        $this->client->loginUser($this->data->user('admin@example.test', [User::ROLE_ADMIN]));

        $this->client->request('GET', '/fr/admin/gerants');
        $this->client->submitForm('Refuser');

        $applicant = $this->reload($applicant);
        self::assertSame(OwnerRequestStatus::Rejected, $applicant->getOwnerRequestStatus());
        self::assertNotContains(User::ROLE_OWNER, $applicant->getRoles());

        $this->client->loginUser($applicant);
        $this->client->request('GET', '/fr/compte/reservations');
        self::assertAnySelectorTextContains('.ms-card', "n'a pas été acceptée");
        $this->client->submitForm('Demander un compte gérant');
        self::assertSame(OwnerRequestStatus::Pending, $this->reload($applicant)->getOwnerRequestStatus());
    }

    public function testOnlyAdministratorsCanReviewRequests(): void
    {
        $applicant = $this->pendingApplicant();
        $this->client->loginUser($this->data->user('owner@example.test', [User::ROLE_OWNER]));

        $this->client->request('GET', '/fr/admin/gerants');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/fr/admin/gerants/'.$applicant->getId().'/approve', ['_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        self::assertNotContains(User::ROLE_OWNER, $this->reload($applicant)->getRoles());
    }

    public function testAnAlreadyReviewedRequestIsNotReviewedTwice(): void
    {
        $applicant = $this->pendingApplicant();
        $this->client->loginUser($this->data->user('admin@example.test', [User::ROLE_ADMIN]));

        // Two administrators open the page, the first one rejects.
        $crawler = $this->client->request('GET', '/fr/admin/gerants');
        $approve = $crawler->selectButton('Valider')->form();
        $this->client->submitForm('Refuser');
        $this->client->submit($approve);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-warning', 'déjà été traitée');
        self::assertSame(OwnerRequestStatus::Rejected, $this->reload($applicant)->getOwnerRequestStatus());
    }

    private function pendingApplicant(): User
    {
        $applicant = $this->data->user('applicant@example.test');
        $applicant->requestOwnerRole(new \DateTimeImmutable('2026-11-21 09:00'));
        $this->entityManager()->flush();

        return $applicant;
    }

    private function reload(User $user): User
    {
        $reloaded = $this->entityManager()->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
