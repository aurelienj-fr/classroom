<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Discipline;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

final class DisciplineTest extends TestCase
{
    public function testLocalizedNameFallsBackToTheDefaultName(): void
    {
        $discipline = (new Discipline())->setName('Danse')->setNameTranslation('en', ' Dance ');

        self::assertSame('Dance', $discipline->getLocalizedName('en'));
        self::assertSame('Danse', $discipline->getLocalizedName('es'));
        self::assertSame('Danse', $discipline->getLocalizedName('fr'));
    }

    public function testEmptyOrDefaultLocaleTranslationsAreNotStored(): void
    {
        $discipline = (new Discipline())->setName('Danse')
            ->setNameTranslation('en', 'Dance')
            ->setNameTranslation('en', '  ')
            ->setNameTranslation('fr', 'Ignored')
            ->setNameTranslation('es', 'Danza');

        self::assertSame(['es' => 'Danza'], $discipline->getNameTranslations());
    }

    public function testTranslatableUsesTheTranslatorLocale(): void
    {
        $discipline = (new Discipline())->setName('Boxe')->setNameTranslation('es', 'Boxeo');
        $translator = new IdentityTranslator();
        $translator->setLocale('es');

        self::assertSame('Boxeo', $discipline->trans($translator));
        self::assertSame('Boxe', $discipline->trans($translator, 'fr'));
    }
}
