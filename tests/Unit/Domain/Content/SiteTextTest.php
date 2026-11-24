<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Content;

use App\Domain\Content\Logo;
use App\Domain\Content\SiteText;
use App\Domain\Shared\Exception\EmptyText;
use App\Domain\Shared\Exception\TextTooLong;
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

    public function testTheFrenchTextsStartEmptyAndStayWhenAnEditLeavesThemOut(): void
    {
        $text = SiteText::initial();

        $text->editIdentity('mossyleaf.studio', 'Little critters.', 'Drawings', '', ' Petites bêtes. ', 'Dessins', 'Illustrations de petites bêtes');
        $text->editIdentity('mossyleaf.studio', 'Tiny critters.', 'Drawings');

        self::assertSame(['Tiny critters.', 'Petites bêtes.', 'Dessins', 'Illustrations de petites bêtes'], [$text->intro(), $text->introFr(), $text->metaDescriptionFr(), $text->searchTitleFr()]);
    }

    public function testAFrenchTextCanBeEmptiedOnItsOwn(): void
    {
        $text = SiteText::initial();
        $text->editAbout('About', 'Text', 'À propos', "Un.\r\n\r\nDeux.");
        $text->editGallery('Drawings', 'Dessins');

        $text->editAbout('About', 'Text', '  ');
        $text->editGallery('Drawings', '');

        self::assertSame(['', "Un.\n\nDeux.", ''], [$text->aboutTitleFr(), $text->aboutTextFr(), $text->galleryTitleFr()]);
    }

    public function testAFrenchTextIsNotLongerThanItsEnglishTwin(): void
    {
        $this->expectException(TextTooLong::class);

        SiteText::initial()->editGallery('Drawings', str_repeat('é', SiteText::MAX_TITLE + 1));
    }

    public function testTheShopNotesAreOptionalInBothLanguages(): void
    {
        $text = SiteText::initial();

        $text->editShopNotes(' Free shipping from 40 € ', '', '', ' TVA non applicable ');

        self::assertSame(['Free shipping from 40 €', '', '', 'TVA non applicable'], [$text->announcement(), $text->announcementFr(), $text->priceNote(), $text->priceNoteFr()]);
    }

    public function testAShopNoteLongerThanItsLineIsRefused(): void
    {
        $this->expectException(TextTooLong::class);

        SiteText::initial()->editShopNotes('', '', '', str_repeat('é', SiteText::MAX_PRICE_NOTE + 1));
    }
}
