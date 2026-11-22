<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Activity;
use App\Entity\ClassSession;
use App\Entity\Studio;
use App\Entity\User;
use App\Schedule\WeekPlanning;
use PHPUnit\Framework\TestCase;

final class WeekPlanningTest extends TestCase
{
    public function testWindowStartsTodayAndGroupsSessionsByDay(): void
    {
        $now = new \DateTimeImmutable('2026-11-22 15:30');
        $activity = new Activity(new Studio(new User()));
        $monday = new ClassSession($activity, new \DateTimeImmutable('2026-11-23 18:30'));
        $mondayLater = new ClassSession($activity, new \DateTimeImmutable('2026-11-23 20:00'));
        $outside = new ClassSession($activity, new \DateTimeImmutable('2026-11-29 10:00'));

        $planning = WeekPlanning::build($now, 0, [$monday, $mondayLater, $outside]);

        self::assertEquals(new \DateTimeImmutable('2026-11-22 00:00'), $planning->start);
        self::assertEquals(new \DateTimeImmutable('2026-11-28 00:00'), $planning->getEnd());
        self::assertCount(7, $planning->days);
        self::assertSame([$monday, $mondayLater], $planning->days['2026-11-23']);
        self::assertArrayNotHasKey('2026-11-29', $planning->days);
        self::assertFalse($planning->isEmpty());
    }

    public function testOffsetMovesTheWindowByWeeks(): void
    {
        $planning = WeekPlanning::build(new \DateTimeImmutable('2026-11-22 15:30'), 2, []);

        self::assertEquals(new \DateTimeImmutable('2026-12-06 00:00'), $planning->start);
        self::assertTrue($planning->isEmpty());
    }
}
