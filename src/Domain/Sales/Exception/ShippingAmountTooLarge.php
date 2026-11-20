<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Sales\ShippingZone;
use App\Domain\Shared\Exception\DomainException;

final class ShippingAmountTooLarge extends DomainException
{
    public function __construct(string $field)
    {
        parent::__construct('sales.amount_too_large', ['field' => $field, 'max' => intdiv(ShippingZone::MAX_AMOUNT, 100)]);
    }
}
