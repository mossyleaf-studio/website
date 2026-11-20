<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Catalogue;

use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\Exception\NegativeAmount;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductVariant;
use App\Domain\Content\Exception\EmptyText;
use App\Domain\Content\Exception\TextTooLong;
use App\Domain\Shared\Locale;
use App\Domain\Shared\Translation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ProductTest extends TestCase
{
    public function testAPushedProductArrivesHiddenWithItsMossytrunkFields(): void
    {
        $sourceId = new Ulid();
        $prints = Category::named(' Prints ', 0);

        $product = Product::pushed($sourceId, ' PRT-FOX ', ' Fox ', ' Prints Fox ', $prints, 1500, 'prints-fox', 4);

        self::assertTrue($sourceId->equals($product->sourceId()));
        self::assertSame(['PRT-FOX', 'Fox', 'Prints Fox', 1500, 'prints-fox', 4], [$product->reference(), $product->name(), $product->displayName(), $product->price(), $product->slug(), $product->position()]);
        self::assertSame($prints, $product->category());
        self::assertFalse($product->isVisible());
        self::assertFalse($product->isRetired());
        self::assertSame(['', ''], [$product->title()->in(Locale::En), $product->description()->in(Locale::Fr)]);
        self::assertSame([], $product->variants());
    }

    public function testAPushOverwritesMossytrunkFieldsAndKeepsTheWebsiteOnes(): void
    {
        $product = self::product();
        $product->describe(Translation::of('Fox print', 'Affiche renard'), Translation::of('A fox among ferns.', ''));
        $product->show();
        $product->moveTo(7);
        $stickers = Category::named('Stickers', 1);

        $product->synchronise('STK-FOX', 'Fox sticker', 'Stickers Fox sticker', $stickers, 300);

        self::assertSame(['STK-FOX', 'Fox sticker', 'Stickers Fox sticker', 300], [$product->reference(), $product->name(), $product->displayName(), $product->price()]);
        self::assertSame($stickers, $product->category());
        self::assertSame(['prints-fox', 'Affiche renard', 'A fox among ferns.', 7], [$product->slug(), $product->title()->in(Locale::Fr), $product->description()->in(Locale::Fr), $product->position()]);
        self::assertTrue($product->isVisible());
    }

    public function testARetiredProductLosesItsVariantsAndComesBackWhenPushedAgain(): void
    {
        $product = self::product();
        $product->stock('PRT-FOX-A4', 'A4', 3, 0);
        $first = new \DateTimeImmutable('2026-11-20 10:00');

        $product->retire($first);
        $product->retire(new \DateTimeImmutable('2026-11-21 10:00'));

        self::assertTrue($product->isRetired());
        self::assertSame($first, $product->retiredAt());
        self::assertSame([], $product->variants());

        $product->synchronise('PRT-FOX', 'Fox', 'Prints Fox', $product->category(), 1500);

        self::assertFalse($product->isRetired());
    }

    public function testVariantsAreRewrittenBySkuInThePushedOrder(): void
    {
        $product = self::product();
        $product->stock('PRT-FOX-A4', 'A4', 3, 0);
        $product->stock('PRT-FOX-A5', 'A5', 8, 1);
        $kept = $product->variants()[1];

        $product->keepVariants(['PRT-FOX-A5', 'PRT-FOX-A3']);
        $product->stock(' PRT-FOX-A3 ', ' A3 ', 1, 0);
        $product->stock('PRT-FOX-A5', 'A5 (148 × 210)', 0, 1);

        self::assertSame(
            [['PRT-FOX-A3', 'A3', 1], ['PRT-FOX-A5', 'A5 (148 × 210)', 0]],
            array_map(static fn (ProductVariant $variant): array => [$variant->sku(), $variant->name(), $variant->stock()], $product->variants()),
        );
        self::assertSame($kept, $product->variants()[1]);
    }

    public function testAProductWithoutVariantsKeepsOneNamelessVariant(): void
    {
        $product = self::product();

        $product->stock('PRT-FOX', ' ', 12, 0);

        self::assertNull($product->variants()[0]->name());
    }

    public function testANegativePriceIsRefused(): void
    {
        $this->expectException(NegativeAmount::class);

        Product::pushed(new Ulid(), 'PRT-FOX', 'Fox', 'Prints Fox', Category::named('Prints', 0), -1, 'prints-fox', 0);
    }

    public function testANegativeStockIsRefused(): void
    {
        $this->expectException(NegativeAmount::class);

        self::product()->stock('PRT-FOX', null, -1, 0);
    }

    public function testAReferenceLongerThanMossytrunkAllowsIsRefused(): void
    {
        $this->expectException(TextTooLong::class);

        self::product()->synchronise(str_repeat('R', Product::MAX_REFERENCE + 1), 'Fox', 'Prints Fox', Category::named('Prints', 0), 1500);
    }

    public function testAProductNeedsAName(): void
    {
        $this->expectException(EmptyText::class);

        self::product()->synchronise('PRT-FOX', ' ', 'Prints Fox', Category::named('Prints', 0), 1500);
    }

    public function testATitleLongerThanItsLimitIsRefused(): void
    {
        $this->expectException(TextTooLong::class);

        self::product()->describe(Translation::of('Fox', str_repeat('é', Product::MAX_TITLE + 1)), Translation::none());
    }

    public function testACategoryWithoutLabelShowsItsMossytrunkName(): void
    {
        self::assertSame('Prints', Category::named('Prints', 0)->labelIn(Locale::Fr));
    }

    private static function product(): Product
    {
        return Product::pushed(new Ulid(), 'PRT-FOX', 'Fox', 'Prints Fox', Category::named('Prints', 0), 1500, 'prints-fox', 0);
    }
}
