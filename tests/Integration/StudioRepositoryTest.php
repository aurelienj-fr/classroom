<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Studio;
use App\Entity\User;
use App\Repository\StudioRepository;
use App\Tests\Support\TestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class StudioRepositoryTest extends KernelTestCase
{
    private TestData $data;
    private StudioRepository $repository;
    private User $owner;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->data = new TestData($container->get(EntityManagerInterface::class), $container->get(UserPasswordHasherInterface::class));
        $this->repository = $container->get(StudioRepository::class);
        $this->owner = $this->data->user('owner@example.test', [User::ROLE_OWNER]);
    }

    public function testLimitCountsStudiosNotJoinedRows(): void
    {
        foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
            $this->data->studio($this->owner, 'Studio '.$letter, 'Rennes')
                ->addDiscipline($this->data->discipline('Pilates'))
                ->addDiscipline($this->data->discipline('Boxe'));
        }
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        static::getContainer()->get(EntityManagerInterface::class)->clear();

        $studios = $this->repository->searchPublished('Rennes', null, 4);

        self::assertSame(['Studio A', 'Studio B', 'Studio C', 'Studio D'], array_map(static fn (Studio $s): string => $s->getName(), $studios));
        foreach ($studios as $studio) {
            self::assertCount(3, $studio->getDisciplines(), 'Each studio keeps all its disciplines.');
        }
    }

    public function testFiltersByDisciplineAndPostalCode(): void
    {
        $this->data->studio($this->owner, 'Yoga only', 'Brest');
        $this->data->studio($this->owner, 'With boxing', 'Brest')->addDiscipline($this->data->discipline('Boxe'));
        $this->data->studio($this->owner, 'Hidden', 'Brest', published: false)->addDiscipline($this->data->discipline('Boxe'));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $byDiscipline = $this->repository->searchPublished('brest', $this->data->discipline('Boxe'));
        self::assertSame(['With boxing'], array_map(static fn (Studio $s): string => $s->getName(), $byDiscipline));

        // TestData studios use postal code 75001; "750" matches by prefix (the unpublished one is excluded).
        self::assertCount(2, $this->repository->searchPublished('750', null));
        self::assertSame([], $this->repository->searchPublished('%', null), 'LIKE wildcards are escaped.');
    }
}
