<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\Exception\NegativeShippingAmount;
use App\Domain\Sales\Exception\ShippingAmountTooLarge;
use App\Domain\Sales\ShippingZone;
use App\Domain\Sales\Zone;
use PHPUnit\Framework\TestCase;

final class ShippingZoneTest extends TestCase
{
    public function testAZoneStartsOffAndFree(): void
    {
        $zone = ShippingZone::off(Zone::Eu);

        self::assertSame([Zone::Eu, 0, null, false], [$zone->zone(), $zone->price(), $zone->freeFrom(), $zone->isEnabled()]);
    }

    public function testAZoneIsSetAndTurnedOn(): void
    {
        $zone = ShippingZone::off(Zone::France);

        $zone->set(650, 6000, true);

        self::assertSame([650, 6000, true], [$zone->price(), $zone->freeFrom(), $zone->isEnabled()]);
    }

    public function testShippingIsFreeFromTheThresholdOnly(): void
    {
        $zone = ShippingZone::off(Zone::France);
        $zone->set(650, 6000, true);

        self::assertSame([false, true, true], [$zone->isFreeFor(5999), $zone->isFreeFor(6000), $zone->isFreeFor(9000)]);
    }

    public function testShippingIsNeverFreeWithoutAThreshold(): void
    {
        $zone = ShippingZone::off(Zone::World);
        $zone->set(1500, null, true);

        self::assertFalse($zone->isFreeFor(1_000_000));
    }

    public function testANegativePriceIsRefused(): void
    {
        $this->expectException(NegativeShippingAmount::class);

        ShippingZone::off(Zone::France)->set(-1, null, true);
    }

    public function testANegativeThresholdIsRefused(): void
    {
        $this->expectException(NegativeShippingAmount::class);

        ShippingZone::off(Zone::France)->set(0, -1, true);
    }

    public function testTheMaximumAmountIsAccepted(): void
    {
        $zone = ShippingZone::off(Zone::France);

        $zone->set(ShippingZone::MAX_AMOUNT, ShippingZone::MAX_AMOUNT, true);

        self::assertSame([ShippingZone::MAX_AMOUNT, ShippingZone::MAX_AMOUNT], [$zone->price(), $zone->freeFrom()]);
    }

    public function testAPriceAboveTheMaximumIsRefused(): void
    {
        $this->expectException(ShippingAmountTooLarge::class);

        ShippingZone::off(Zone::France)->set(ShippingZone::MAX_AMOUNT + 1, null, true);
    }

    public function testAThresholdAboveTheMaximumIsRefused(): void
    {
        $this->expectException(ShippingAmountTooLarge::class);

        ShippingZone::off(Zone::France)->set(0, ShippingZone::MAX_AMOUNT + 1, true);
    }
}
