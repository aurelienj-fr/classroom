<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\OwnerRequestStatus;
use App\Tests\Support\TestData;

final class PublicPagesTest extends WebTestCaseHelper
{
    public function testHomePageListsUpcomingSessions(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]), 'Atelier Soleil');
        $this->data->session($studio, new \DateTimeImmutable('2026-11-23 18:30'), name: 'Yin yoga');

        $this->client->request('GET', '/fr');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Votre prochain cours');
        self::assertAnySelectorTextContains('.ms-card', 'Yin yoga');
    }

    public function testSearchFiltersPublishedStudiosByCity(): void
    {
        $owner = $this->data->user('owner@example.test', [User::ROLE_OWNER]);
        $this->data->studio($owner, 'Studio Lyonnais', 'Lyon');
        $this->data->studio($owner, 'Studio Parisien', 'Paris');
        $this->data->studio($owner, 'Studio Caché', 'Lyon', published: false);

        $crawler = $this->client->request('GET', '/fr/studios?ville=lyo');

        self::assertResponseIsSuccessful();
        $names = $crawler->filter('h2.h5')->each(static fn ($node): string => $node->text());
        self::assertSame(['Studio Lyonnais'], $names);
    }

    public function testStudioPageShowsTheWeeklyPlanning(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]), 'Atelier Soleil', 'Nantes');
        $this->data->session($studio, new \DateTimeImmutable('2026-11-24 12:30'), name: 'Pilates');

        $this->client->request('GET', '/fr/studios/'.$studio->getSlug());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Atelier Soleil');
        self::assertSelectorTextContains('.ms-day-title', 'mardi 24 novembre');
        self::assertAnySelectorTextContains('.ms-session', 'Pilates');
    }

    public function testUnpublishedStudioIsNotFound(): void
    {
        $studio = $this->data->studio($this->data->user('owner@example.test', [User::ROLE_OWNER]), published: false);

        $this->client->request('GET', '/fr/studios/'.$studio->getSlug());

        self::assertResponseStatusCodeSame(404);
    }

    public function testOwnerRegistrationCreatesAPendingRequestOnly(): void
    {
        $this->client->request('GET', '/fr/inscription');
        $this->client->submitForm('Créer mon compte', [
            'registration_form[firstName]' => 'Zoé',
            'registration_form[lastName]' => 'Lambert',
            'registration_form[email]' => 'Zoe@Example.test',
            'registration_form[plainPassword][first]' => 'un-mot-de-passe-solide',
            'registration_form[plainPassword][second]' => 'un-mot-de-passe-solide',
            'registration_form[studioOwner]' => true,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseRedirects('/fr/compte/reservations');
        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'zoe@example.test']);
        self::assertNotNull($user);
        self::assertNotContains(User::ROLE_OWNER, $user->getRoles(), 'An administrator must validate owners.');
        self::assertSame(OwnerRequestStatus::Pending, $user->getOwnerRequestStatus());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-info', 'demande de compte gérant a été envoyée');

        $this->client->request('GET', '/fr/gestion');
        self::assertResponseStatusCodeSame(403);
    }

    public function testRegistrationRejectsShortPasswords(): void
    {
        $this->client->request('GET', '/fr/inscription');
        $this->client->submitForm('Créer mon compte', [
            'registration_form[firstName]' => 'Zoé',
            'registration_form[lastName]' => 'Lambert',
            'registration_form[email]' => 'zoe@example.test',
            'registration_form[plainPassword][first]' => 'court',
            'registration_form[plainPassword][second]' => 'court',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', '12 caractères');
    }

    public function testLoginWithValidCredentials(): void
    {
        $this->data->user('member@example.test');

        $this->client->request('GET', '/fr/connexion');
        $this->client->submitForm('Se connecter', [
            '_username' => 'member@example.test',
            '_password' => TestData::PASSWORD,
        ]);

        self::assertResponseRedirects('/fr');
        $this->client->followRedirect();
        self::assertSelectorTextContains('nav', 'Mes réservations');
    }

    public function testLoginIgnoresExternalTargetPath(): void
    {
        $this->data->user('member@example.test');

        $this->client->request('POST', '/fr/connexion', [
            '_username' => 'member@example.test',
            '_password' => TestData::PASSWORD,
            '_csrf_token' => $this->csrfToken('authenticate'),
            '_target_path' => 'https://evil.example/phishing',
        ]);

        // Symfony restricts post-login redirects to the session domain and falls back to "/".
        self::assertResponseRedirects('/');
    }

    private function csrfToken(string $id): string
    {
        // Render the login page first so the token is stored in the test session.
        $crawler = $this->client->request('GET', '/fr/connexion');

        return (string) $crawler->filter('input[name="_csrf_token"]')->attr('value');
    }
}
