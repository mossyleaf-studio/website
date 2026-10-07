<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Content;

use App\Domain\Content\Exception\EmptyText;
use App\Domain\Content\Exception\TextTooLong;
use App\Domain\Content\Logo;
use App\Domain\Content\SiteText;
use PHPUnit\Framework\TestCase;

final class SiteTextTest extends TestCase
{
    public function testTextsAreTrimmed(): void
    {
        $text = SiteText::initial();

        $text->editIdentity('  mossyleaf.studio ', ' Little critters. ', ' Drawings ');

        self::assertSame(['mossyleaf.studio', 'Little critters.', 'Drawings'], [$text->studioName(), $text->intro(), $text->metaDescription()]);
    }

    public function testAnEmptyTextIsRefused(): void
    {
        $this->expectException(EmptyText::class);

        SiteText::initial()->editHomeNote(" \n ", 'Text');
    }

    public function testATextLongerThanItsSectionAllowsIsRefused(): void
    {
        $this->expectException(TextTooLong::class);

        SiteText::initial()->editIdentity('mossyleaf.studio', str_repeat('é', SiteText::MAX_INTRO + 1), 'Drawings');
    }

    public function testALogoHandsBackTheOneItReplaces(): void
    {
        $text = SiteText::initial();
        self::assertNull($text->logo());

        self::assertNull($text->useLogo(new Logo('first.webp', 800, 600)));
        $previous = $text->useLogo(new Logo('second.webp', 400, 400));

        self::assertSame('first.webp', $previous?->file);
        self::assertSame('second.webp', $text->removeLogo()?->file);
        self::assertNull($text->logo());
    }

    public function testTheAboutTextSplitsIntoParagraphsOnEmptyLines(): void
    {
        $text = SiteText::initial();

        $text->editAbout('About', "First line\nstill first.\r\n\r\n  \n\nSecond.");

        self::assertSame(["First line\nstill first.", 'Second.'], $text->aboutParagraphs());
    }
}
