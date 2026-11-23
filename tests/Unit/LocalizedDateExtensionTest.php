<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Twig\LocalizedDateExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;

final class LocalizedDateExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function formats(): iterable
    {
        yield 'fr day' => ['fr', 'day', 'lundi 23 novembre'];
        yield 'fr day_short' => ['fr', 'day_short', 'lun. 23 nov.'];
        yield 'fr date' => ['fr', 'date', '23 novembre 2026'];
        yield 'fr full' => ['fr', 'full', 'lundi 23 novembre 2026 à 18:05'];
        yield 'en day' => ['en', 'day', 'Monday, November 23'];
        yield 'en day_short' => ['en', 'day_short', 'Mon, Nov 23'];
        yield 'en date' => ['en', 'date', 'November 23, 2026'];
        yield 'en full' => ['en', 'full', 'Monday, November 23, 2026 at 18:05'];
        yield 'es day' => ['es', 'day', 'lunes 23 de noviembre'];
        yield 'es day_short' => ['es', 'day_short', 'lun. 23 nov.'];
        yield 'es date' => ['es', 'date', '23 de noviembre de 2026'];
        yield 'es full' => ['es', 'full', 'lunes 23 de noviembre de 2026 a las 18:05'];
        yield 'time' => ['en', 'time', '18:05'];
        yield 'regional locale' => ['en_GB', 'day', 'Monday, November 23'];
        yield 'unsupported locale falls back to French' => ['de', 'day', 'lundi 23 novembre'];
    }

    #[DataProvider('formats')]
    public function testFormat(string $locale, string $format, string $expected): void
    {
        $date = new \DateTimeImmutable('2026-11-23 18:05');

        self::assertSame($expected, $this->extension($locale)->format($date, $format));
    }

    public function testExplicitLocaleOverridesTheCurrentOne(): void
    {
        $date = new \DateTimeImmutable('2026-11-23 18:05');

        self::assertSame('lunes 23 de noviembre', $this->extension('fr')->format($date, 'day', 'es'));
    }

    public function testUnknownFormatIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->extension('fr')->format(new \DateTimeImmutable(), 'nope');
    }

    private function extension(string $locale): LocalizedDateExtension
    {
        return new LocalizedDateExtension(new LocaleSwitcher($locale, []));
    }
}
