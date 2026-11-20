<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Shared\Exception\DomainException;

final class NegativeShippingAmount extends DomainException
{
    public function __construct(string $field)
    {
        parent::__construct('sales.negative_amount', ['field' => $field]);
    }
}
