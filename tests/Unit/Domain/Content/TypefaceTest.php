<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Content;

use App\Domain\Content\Exception\UnknownFont;
use App\Domain\Content\FontRole;
use App\Domain\Content\SiteText;
use App\Domain\Content\Typeface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TypefaceTest extends TestCase
{
    public function testAFamilyNameIsTidied(): void
    {
        self::assertSame('Patrick Hand', Typeface::family("  Patrick \t Hand "));
    }

    /** @return iterable<string, array{string}> */
    public static function impossibleNames(): iterable
    {
        yield 'empty' => [''];
        yield 'quote' => ["Caveat'"];
        yield 'css' => ['Caveat; color: red'];
        yield 'too long' => [str_repeat('a', Typeface::MAX_FAMILY + 1)];
    }

    #[DataProvider('impossibleNames')]
    public function testANameNoGoogleFontCanHaveIsRefused(string $family): void
    {
        $this->expectException(UnknownFont::class);

        Typeface::family($family);
    }

    public function testEachRoleKeepsItsOwnFontUntilItGoesBackToTheSiteFont(): void
    {
        $text = SiteText::initial();
        self::assertNull($text->font(FontRole::Heading));

        $text->useFont(FontRole::Heading, new Typeface('Caveat', 'heading.css', 700));
        $text->useFont(FontRole::Body, new Typeface('Nunito', 'body.css', 400));
        $text->useFont(FontRole::Heading, null);

        self::assertNull($text->font(FontRole::Heading));
        self::assertEquals(new Typeface('Nunito', 'body.css', 400), $text->font(FontRole::Body));
    }
}
