<?php

declare(strict_types=1);

namespace App\Application\Sales\EditShippingZone;

use App\Application\Sales\ShippingZoneView;
use App\Application\Transaction;
use App\Domain\Sales\ShippingZoneRepository;

final readonly class EditShippingZoneHandler
{
    public function __construct(
        private ShippingZoneRepository $zones,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditShippingZone $command): ShippingZoneView
    {
        $zone = $this->zones->get($command->zone);
        $zone->set($command->price, $command->freeFrom, $command->enabled);
        $this->transaction->commit();

        return ShippingZoneView::of($zone);
    }
}
