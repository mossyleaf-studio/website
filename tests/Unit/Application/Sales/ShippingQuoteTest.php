<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Sales\ShippingQuote;
use App\Domain\Sales\ShippingZone;
use App\Domain\Sales\Zone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShippingQuoteTest extends TestCase
{
    /** @return iterable<string, array{?int, int, int, int, bool}> */
    public static function carts(): iterable
    {
        yield 'under the threshold pays the price' => [6000, 5999, 1, 650, false];
        yield 'at the threshold is free' => [6000, 6000, 1, 0, true];
        yield 'above the threshold is free' => [6000, 9000, 3, 0, true];
        yield 'without a threshold the price is always due' => [null, 1_000_000, 4, 650, false];
        yield 'an empty cart owes nothing and is not free' => [6000, 0, 0, 0, false];
        yield 'an empty cart with a zero threshold is not free either' => [0, 0, 0, 0, false];
        yield 'a zero threshold makes a filled cart free' => [0, 100, 1, 0, true];
    }

    #[DataProvider('carts')]
    public function testShippingIsPricedFromTheGoodsAndTheUnits(?int $freeFrom, int $goods, int $units, int $price, bool $free): void
    {
        $zone = ShippingZone::off(Zone::France);
        $zone->set(650, $freeFrom, true);

        $quote = ShippingQuote::of($zone, $goods, $units);

        self::assertSame(['france', $price, $freeFrom, $free], [$quote->zone, $quote->price, $quote->freeFrom, $quote->free]);
    }
}
