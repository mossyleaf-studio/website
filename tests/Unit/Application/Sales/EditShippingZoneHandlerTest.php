<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sales;

use App\Application\Sales\EditShippingZone\EditShippingZone;
use App\Application\Sales\EditShippingZone\EditShippingZoneHandler;
use App\Application\Transaction;
use App\Domain\Sales\Exception\ShippingAmountTooLarge;
use App\Domain\Sales\ShippingZone;
use App\Domain\Sales\ShippingZoneRepository;
use App\Domain\Sales\Zone;
use PHPUnit\Framework\TestCase;

final class EditShippingZoneHandlerTest extends TestCase
{
    public function testTheZoneIsSetCommittedAndShown(): void
    {
        $transaction = self::transaction();

        $view = (new EditShippingZoneHandler(self::zones(), $transaction))(new EditShippingZone(Zone::Eu, 950, 8000, true));

        self::assertSame(['eu', 950, 8000, true], [$view->code, $view->price, $view->freeFrom, $view->enabled]);
        self::assertSame(1, $transaction->commits);
    }

    public function testAnAmountAboveTheMaximumIsNotCommitted(): void
    {
        $transaction = self::transaction();

        try {
            (new EditShippingZoneHandler(self::zones(), $transaction))(new EditShippingZone(Zone::France, ShippingZone::MAX_AMOUNT + 1, null, true));
            self::fail('The amount was accepted.');
        } catch (ShippingAmountTooLarge) {
            self::assertSame(0, $transaction->commits);
        }
    }

    private static function zones(): ShippingZoneRepository
    {
        return new class implements ShippingZoneRepository {
            /** @var array<string, ShippingZone> */
            private array $zones = [];

            public function get(Zone $zone): ShippingZone
            {
                return $this->zones[$zone->value] ??= ShippingZone::off($zone);
            }

            public function all(): array
            {
                return array_map($this->get(...), Zone::cases());
            }
        };
    }

    /** @return Transaction&object{commits: int} */
    private static function transaction(): Transaction
    {
        return new class implements Transaction {
            public int $commits = 0;

            public function commit(): void
            {
                ++$this->commits;
            }
        };
    }
}
