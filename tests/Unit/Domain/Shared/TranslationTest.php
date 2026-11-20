<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Shared\Locale;
use App\Domain\Shared\Translation;
use PHPUnit\Framework\TestCase;

final class TranslationTest extends TestCase
{
    public function testEachLanguageReadsItsOwnText(): void
    {
        $title = Translation::of(' Fox print ', ' Affiche renard ');

        self::assertSame(['Fox print', 'Affiche renard'], [$title->in(Locale::En), $title->in(Locale::Fr)]);
    }

    public function testAnEmptyLanguageFallsBackToTheOther(): void
    {
        self::assertSame('Fox print', Translation::of('Fox print', ' ')->in(Locale::Fr));
        self::assertSame('Affiche renard', Translation::of('', 'Affiche renard')->in(Locale::En));
    }

    public function testNoTextAtAllReadsEmpty(): void
    {
        self::assertSame(['', ''], [Translation::none()->in(Locale::En), Translation::none()->in(Locale::Fr)]);
    }

    public function testTheLongestLanguageDecidesWhetherItFits(): void
    {
        $title = Translation::of('Fox', 'Renard');

        self::assertFalse($title->exceeds(6));
        self::assertTrue($title->exceeds(5));
    }
}
