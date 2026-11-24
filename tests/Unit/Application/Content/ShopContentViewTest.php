<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Content;

use App\Application\Content\ShopContentView;
use App\Domain\Content\Link;
use App\Domain\Content\SiteText;
use App\Domain\Content\TapeTone;
use PHPUnit\Framework\TestCase;

final class ShopContentViewTest extends TestCase
{
    public function testAnEmptyFrenchTextReadsInEnglish(): void
    {
        $text = SiteText::initial();
        $link = Link::create('Shop on Etsy', 'Prints', 'https://www.etsy.com/shop/mossyleafstudio', TapeTone::Leaf, 0);

        $shop = ShopContentView::of($text, [$link], []);

        self::assertSame([$text->intro(), $text->intro()], [$shop->intro->en, $shop->intro->fr]);
        self::assertSame('Recent drawings', $shop->galleryTitle->fr);
        self::assertSame('Shop on Etsy', $shop->links[0]->title->fr);
    }

    public function testAShopNoteWrittenInOneLanguageOnlyReadsInBoth(): void
    {
        $text = SiteText::initial();
        $text->editShopNotes('', 'Livraison offerte dès 40 €', '', '');

        $shop = ShopContentView::of($text, [], []);

        self::assertSame(['Livraison offerte dès 40 €', 'Livraison offerte dès 40 €'], [$shop->announcement->en, $shop->announcement->fr]);
        self::assertSame(['', ''], [$shop->priceNote->en, $shop->priceNote->fr]);
    }

    public function testAnEmptyEnglishSearchTitleReadsAsTheStudioNameAndNeverAsTheFrenchOne(): void
    {
        $text = SiteText::initial();

        $text->editIdentity('mossyleaf.studio', 'Little critters.', 'Drawings', '', searchTitleFr: 'Illustrations');
        $frenchOnly = ShopContentView::of($text, [], [])->pageTitle;
        $text->editIdentity('mossyleaf.studio', 'Little critters.', 'Drawings', 'Critter illustrations', searchTitleFr: '');
        $englishOnly = ShopContentView::of($text, [], [])->pageTitle;
        $text->editIdentity('mossyleaf.studio', 'Little critters.', 'Drawings', '', searchTitleFr: '');
        $neither = ShopContentView::of($text, [], [])->pageTitle;

        self::assertSame(['mossyleaf.studio', 'Illustrations'], [$frenchOnly->en, $frenchOnly->fr]);
        self::assertSame(['Critter illustrations', 'Critter illustrations'], [$englishOnly->en, $englishOnly->fr]);
        self::assertSame(['mossyleaf.studio', 'mossyleaf.studio'], [$neither->en, $neither->fr]);
    }
}
