<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Support\TestData;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProfileTest extends WebTestCaseHelper
{
    public function testMemberChangesPasswordAndStaysLoggedIn(): void
    {
        $member = $this->data->user('member@example.test');
        $this->client->loginUser($member);

        $this->client->request('GET', '/fr/compte/profil');
        $this->client->submitForm('Changer le mot de passe', [
            'change_password[currentPassword]' => TestData::PASSWORD,
            'change_password[newPassword][first]' => 'un-nouveau-mot-de-passe',
            'change_password[newPassword][second]' => 'un-nouveau-mot-de-passe',
        ]);

        self::assertResponseRedirects('/fr/compte/profil');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-success', 'mot de passe a été changé');

        $reloaded = $this->entityManager()->getRepository(User::class)->find($member->getId());
        self::assertNotNull($reloaded);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($reloaded, 'un-nouveau-mot-de-passe'));
    }

    public function testWrongCurrentPasswordIsRejected(): void
    {
        $this->client->loginUser($this->data->user('member@example.test'));

        $this->client->request('GET', '/fr/compte/profil');
        $this->client->submitForm('Changer le mot de passe', [
            'change_password[currentPassword]' => 'pas-le-bon-mot-de-passe',
            'change_password[newPassword][first]' => 'un-nouveau-mot-de-passe',
            'change_password[newPassword][second]' => 'un-nouveau-mot-de-passe',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'mot de passe actuel est incorrect');
    }

    public function testMemberChangesEmailAndCanLogInWithIt(): void
    {
        $member = $this->data->user('member@example.test');
        $this->client->loginUser($member);

        $this->client->request('GET', '/fr/compte/profil');
        $this->client->submitForm('Enregistrer', [
            'profile[firstName]' => 'Léa',
            'profile[lastName]' => 'Bernard',
            'profile[email]' => 'Lea@Example.test',
        ]);

        self::assertResponseRedirects('/fr/compte/profil');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('nav', 'Léa');

        // New login works with the new e-mail.
        $this->client->getCookieJar()->clear(); // new browser session
        $this->client->request('GET', '/fr/connexion');
        $this->client->submitForm('Se connecter', ['_username' => 'lea@example.test', '_password' => TestData::PASSWORD]);
        self::assertResponseRedirects('/fr');
    }

    public function testEmailAlreadyUsedIsRejected(): void
    {
        $this->data->user('taken@example.test');
        $member = $this->data->user('member@example.test');
        $this->client->loginUser($member);

        $this->client->request('GET', '/fr/compte/profil');
        $this->client->submitForm('Enregistrer', ['profile[email]' => 'taken@example.test']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Un compte existe déjà');
        self::assertSame('member@example.test', $this->entityManager()->getRepository(User::class)->find($member->getId())?->getEmail());
    }

    public function testAnonymousVisitorIsSentToLogin(): void
    {
        $this->client->request('GET', '/fr/compte/profil');

        self::assertResponseRedirects('/fr/connexion');
    }
}
