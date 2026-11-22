<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ClassSession;
use App\Entity\User;
use App\Schedule\SessionScheduler;
use App\Tests\Support\TestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SessionSchedulerTest extends KernelTestCase
{
    public function testScheduleCreatesWeeklyRepetitions(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $data = new TestData($container->get(EntityManagerInterface::class), $container->get(UserPasswordHasherInterface::class));
        $template = $data->session($data->studio($data->user('owner@example.test', [User::ROLE_OWNER])), new \DateTimeImmutable('2026-11-23 18:00'), capacity: 7);
        $template->setInstructorName('Claire');

        $sessions = $container->get(SessionScheduler::class)->schedule($template, 3);

        self::assertCount(4, $sessions);
        self::assertSame(
            ['2026-11-23 18:00', '2026-11-30 18:00', '2026-12-07 18:00', '2026-12-14 18:00'],
            array_map(static fn (ClassSession $s): string => $s->getStartsAt()->format('Y-m-d H:i'), $sessions),
        );
        foreach ($sessions as $session) {
            self::assertNotNull($session->getId());
            self::assertSame(7, $session->getCapacity());
            self::assertSame('Claire', $session->getInstructorName());
        }
    }

    public function testRejectsTooManyRepetitions(): void
    {
        self::bootKernel();
        $scheduler = static::getContainer()->get(SessionScheduler::class);
        $session = $this->createStub(ClassSession::class);

        $this->expectException(\InvalidArgumentException::class);
        $scheduler->schedule($session, SessionScheduler::MAX_WEEKLY_REPETITIONS + 1);
    }
}
