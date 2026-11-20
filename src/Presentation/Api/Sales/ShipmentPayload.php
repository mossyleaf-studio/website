<?php

declare(strict_types=1);

namespace App\Presentation\Api\Sales;

use App\Domain\Sales\Order;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ShipmentPayload
{
    public function __construct(
        #[Assert\Length(max: Order::MAX_CODE, maxMessage: 'text.too_long', normalizer: 'trim')]
        #[Assert\Regex(pattern: Order::CONTROL_CHARACTERS, match: false, message: 'text.line_break', normalizer: 'trim')]
        public ?string $trackingNumber = null,
    ) {
    }
}
