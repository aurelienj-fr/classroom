<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateAdminCommandTest extends KernelTestCase
{
    public function testCreatesAnAdministrator(): void
    {
        $tester = $this->tester();
        $tester->setInputs(['un-mot-de-passe-solide']);

        $tester->execute(['email' => 'Chef@Example.test', 'first-name' => 'Chef', 'last-name' => 'Admin']);

        $tester->assertCommandIsSuccessful();
        $user = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'chef@example.test']);
        self::assertNotNull($user);
        self::assertContains(User::ROLE_ADMIN, $user->getRoles());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'un-mot-de-passe-solide'));
    }

    public function testPrintsSqlWithAHashedPassword(): void
    {
        $tester = $this->tester();
        $tester->setInputs(['un-mot-de-passe-solide']);

        $tester->execute(['email' => 'chef@example.test', 'first-name' => 'Chef', 'last-name' => "O'Brien", '--sql' => true, '--locale' => 'en']);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
        self::assertStringContainsString('INSERT INTO `user`', $output);
        self::assertStringNotContainsString('un-mot-de-passe-solide', $output, 'Never print the clear password.');
        self::assertStringContainsString("'O\\'Brien'", $output, 'Values are escaped.');
        self::assertNull(static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'chef@example.test']));
    }

    public function testRejectsInvalidInput(): void
    {
        $tester = $this->tester();
        $tester->setInputs(['un-mot-de-passe-solide']);

        $tester->execute(['email' => 'not-an-email', 'first-name' => 'Chef', 'last-name' => 'Admin']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('email', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::bootKernel());

        return new CommandTester($application->find('app:admin:create'));
    }
}
