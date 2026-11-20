<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Application\Content\ShopContentView;
use App\Application\Content\ShopLegalPageView;
use App\Application\Content\SiteSnapshots;
use App\Domain\Content\Artwork;
use App\Domain\Content\FontRole;
use App\Domain\Content\LegalKind;
use App\Domain\Content\LegalPage;
use App\Domain\Content\Link;
use App\Domain\Content\Logo;
use App\Domain\Content\SiteText;
use App\Domain\Content\TapeTone;
use App\Domain\Content\Typeface;
use App\Domain\Shared\Translation;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class ShopSnapshotsTest extends KernelTestCase
{
    public function testAFrozenShopDocumentReadsBackAsItWasWritten(): void
    {
        $text = SiteText::initial();
        $text->editShopNotes('Free shipping from 40 €', 'Livraison offerte dès 40 €', '', 'TVA non applicable');
        $text->useLogo(new Logo('logo.webp', 800, 600));
        $text->useFont(FontRole::Heading, new Typeface('Caveat', 'caveat.css', 700));
        $artwork = Artwork::upload(new Ulid(), 'fern.webp', 1600, 1200, 'A fern', 0, new \DateTimeImmutable());
        $artwork->feature();
        $terms = LegalPage::blank(LegalKind::Terms);
        $terms->write(Translation::of("## Terms\n\nPay first.", ''), new \DateTimeImmutable());
        $shop = ShopContentView::of($text, [Link::create('Shop on Etsy', 'Prints', 'https://www.etsy.com/shop/mossyleafstudio', TapeTone::Blossom, 0, 'Boutique Etsy')], [$artwork], [$terms, LegalPage::blank(LegalKind::Privacy)]);
        $snapshots = self::getContainer()->get(SiteSnapshots::class);

        $read = $snapshots->decodeShop($snapshots->encodeShop($shop));

        self::assertEquals($shop, $read);
        self::assertSame(['Boutique Etsy', 'blossom', true, 'Caveat'], [$read->links[0]->title->fr, $read->links[0]->tape, $read->artworks[0]->featured, $read->headingFont?->family]);
        self::assertSame([['terms', "## Terms\n\nPay first."], ['privacy', '']], array_map(static fn (ShopLegalPageView $page): array => [$page->kind, $page->text->fr], $read->legal));
    }

    public function testADocumentFrozenBeforeAFieldExistedStillReads(): void
    {
        $shop = self::getContainer()->get(SiteSnapshots::class)->decodeShop('{"studioName":"mossyleaf.studio","intro":{"en":"Little critters.","fr":"Petites bêtes."},"retired":"field"}');

        self::assertSame(['mossyleaf.studio', 'Petites bêtes.', '', [], null, []], [$shop->studioName, $shop->intro->fr, $shop->announcement->en, $shop->links, $shop->logo, $shop->legal]);
    }
}
