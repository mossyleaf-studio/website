<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Catalogue;

use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductVariant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ProductVariantTest extends TestCase
{
    /** @return iterable<string, array{int, int, int, int}> */
    public static function takes(): iterable
    {
        yield 'less than the stock' => [5, 2, 2, 3];
        yield 'exactly the stock' => [5, 5, 5, 0];
        yield 'more than the stock gives only what is there' => [5, 8, 5, 0];
        yield 'from nothing' => [0, 3, 0, 0];
        yield 'nothing' => [5, 0, 0, 5];
        yield 'a negative quantity takes nothing' => [5, -2, 0, 5];
    }

    #[DataProvider('takes')]
    public function testTakingReturnsWhatTheVariantReallyGaveAndNeverGoesBelowZero(int $stock, int $quantity, int $taken, int $left): void
    {
        $variant = self::variant($stock);

        self::assertSame($taken, $variant->take($quantity));
        self::assertSame($left, $variant->stock());
    }

    public function testGivingBackAddsTheUnitsAgain(): void
    {
        $variant = self::variant(5);
        $taken = $variant->take(8);

        $variant->giveBack($taken);
        $variant->giveBack(2);

        self::assertSame(7, $variant->stock());
    }

    private static function variant(int $stock): ProductVariant
    {
        return new ProductVariant(Product::pushed(new Ulid(), 'PRT-FOX', 'Fox', 'Prints Fox', Category::named('Prints', 0), 1500, 'prints-fox', 0), 'PRT-FOX-A4', 'A4', $stock, 0);
    }
}
