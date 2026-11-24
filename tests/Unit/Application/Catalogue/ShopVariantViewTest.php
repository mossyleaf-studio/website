<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Catalogue;

use App\Application\Catalogue\ShopVariantView;
use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductVariant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ShopVariantViewTest extends TestCase
{
    /** @return iterable<string, array{int, int, int, string}> */
    public static function stocks(): iterable
    {
        yield 'plenty is capped at ten' => [250, 0, 10, 'inStock'];
        yield 'eleven is capped at ten' => [11, 0, 10, 'inStock'];
        yield 'ten is shown whole' => [10, 0, 10, 'inStock'];
        yield 'four is still in stock' => [4, 0, 4, 'inStock'];
        yield 'three is the last pieces' => [3, 0, 3, 'lastPieces'];
        yield 'one is the last pieces' => [1, 0, 1, 'lastPieces'];
        yield 'none is sold out' => [0, 0, 0, 'soldOut'];
        yield 'what pending orders hold is taken off' => [5, 2, 3, 'lastPieces'];
        yield 'the cap applies after what is held' => [12, 1, 10, 'inStock'];
        yield 'all held is sold out' => [5, 5, 0, 'soldOut'];
        yield 'more held than in stock never goes below zero' => [2, 7, 0, 'soldOut'];
    }

    #[DataProvider('stocks')]
    public function testTheAvailableStockIsCappedAndNeverNegative(int $stock, int $held, int $available, string $state): void
    {
        $view = ShopVariantView::of(self::variant('PRT-FOX', 'A4', $stock), false, $held);

        self::assertSame([$available, 0 === $available, $state], [$view->available, $view->soldOut, $view->state]);
    }

    public function testAnUnnamedVariantIsNamedByItsSkuOnlyAmongSeveral(): void
    {
        $unnamed = self::variant('PRT-FOX-X', null, 1);
        $named = self::variant('PRT-FOX-A4', 'A4', 1);

        self::assertNull(ShopVariantView::of($unnamed, false, 0)->name);
        self::assertSame('PRT-FOX-X', ShopVariantView::of($unnamed, true, 0)->name);
        self::assertSame('A4', ShopVariantView::of($named, true, 0)->name);
        self::assertSame('A4', ShopVariantView::of($named, false, 0)->name);
    }

    private static function variant(string $sku, ?string $name, int $stock): ProductVariant
    {
        return new ProductVariant(Product::pushed(new Ulid(), 'PRT-FOX', 'Fox', 'Prints Fox', Category::named('Prints', 0), 1500, 'prints-fox', 0), $sku, $name, $stock, 0);
    }
}
