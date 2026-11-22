<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Twig\FrenchDateExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrenchDateExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formats(): iterable
    {
        yield 'day' => ['day', 'lundi 23 novembre'];
        yield 'day_short' => ['day_short', 'lun. 23 nov.'];
        yield 'date' => ['date', '23 novembre 2026'];
        yield 'time' => ['time', '18:05'];
        yield 'full' => ['full', 'lundi 23 novembre 2026 à 18:05'];
    }

    #[DataProvider('formats')]
    public function testFormat(string $format, string $expected): void
    {
        $date = new \DateTimeImmutable('2026-11-23 18:05');

        self::assertSame($expected, (new FrenchDateExtension())->format($date, $format));
    }

    public function testUnknownFormatIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FrenchDateExtension())->format(new \DateTimeImmutable(), 'nope');
    }
}
