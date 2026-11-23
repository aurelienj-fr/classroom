<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Discipline;
use App\Entity\User;
use App\Tests\Support\TestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SlugListenerTest extends KernelTestCase
{
    public function testDisciplineSlugsAreUniqueEvenWithinTheSameFlush(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $first = (new Discipline())->setName('Aqua gym')->setIcon('water');
        $second = (new Discipline())->setName('Aqua-gym !')->setIcon('water');
        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->flush();

        self::assertSame('aqua-gym', $first->getSlug());
        self::assertSame('aqua-gym-2', $second->getSlug());
    }

    public function testNamesWithoutLettersGetAFallbackSlug(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $data = new TestData($entityManager, $container->get(UserPasswordHasherInterface::class));

        $discipline = (new Discipline())->setName('★★★')->setIcon('star');
        $entityManager->persist($discipline);
        $entityManager->flush();
        $studio = $data->studio($data->user('owner@example.test', [User::ROLE_OWNER]), '★', '…');

        self::assertSame('discipline', $discipline->getSlug());
        self::assertSame('studio', $studio->getSlug());
    }
}
