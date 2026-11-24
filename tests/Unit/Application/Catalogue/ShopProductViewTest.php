<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Catalogue;

use App\Application\Catalogue\ShopCategoryView;
use App\Application\Catalogue\ShopProductView;
use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\Product;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ShopProductViewTest extends TestCase
{
    public function testAProductIsSoldOutWhenNoVariantCanBeBought(): void
    {
        $product = self::product(['PRT-FOX-A4' => 3, 'PRT-FOX-A5' => 1]);

        self::assertFalse(self::view($product, [])->soldOut);
        self::assertFalse(self::view($product, ['PRT-FOX-A4' => 3])->soldOut);
        self::assertTrue(self::view($product, ['PRT-FOX-A4' => 3, 'PRT-FOX-A5' => 1])->soldOut);
    }

    public function testAProductWithoutVariantIsSoldOut(): void
    {
        self::assertTrue(self::view(self::product([]), [])->soldOut);
    }

    public function testEachVariantLosesTheUnitsHeldOnItsOwnSkuOnly(): void
    {
        $view = self::view(self::product(['PRT-FOX-A4' => 5, 'PRT-FOX-A5' => 5]), ['PRT-FOX-A4' => 2]);

        self::assertSame([3, 5], array_map(static fn ($variant): int => $variant->available, $view->variants));
    }

    /** @param array<string, int> $stocks */
    private static function product(array $stocks): Product
    {
        $product = Product::pushed(new Ulid(), 'PRT-FOX', 'Fox', 'Prints Fox', Category::named('Prints', 0), 1500, 'prints-fox', 0);
        $position = 0;
        foreach ($stocks as $sku => $stock) {
            $product->stock((string) $sku, 'V'.$position, $stock, $position++);
        }

        return $product;
    }

    /** @param array<string, int> $held */
    private static function view(Product $product, array $held): ShopProductView
    {
        return ShopProductView::of($product, ShopCategoryView::of($product->category(), 'prints'), null, $held);
    }
}
