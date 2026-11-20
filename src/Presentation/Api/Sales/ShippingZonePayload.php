<?php

declare(strict_types=1);

namespace App\Presentation\Api\Sales;

use App\Domain\Sales\ShippingZone;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ShippingZonePayload
{
    public function __construct(
        #[Assert\PositiveOrZero(message: 'amount.negative')]
        #[Assert\LessThanOrEqual(ShippingZone::MAX_AMOUNT, message: 'amount.too_large')]
        public int $price = 0,
        #[Assert\PositiveOrZero(message: 'amount.negative')]
        #[Assert\LessThanOrEqual(ShippingZone::MAX_AMOUNT, message: 'amount.too_large')]
        public ?int $freeFrom = null,
        public bool $enabled = false,
    ) {
    }
}
