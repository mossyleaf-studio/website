<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Shared\Exception\DomainException;

final class CartTooLarge extends DomainException
{
    public function __construct(int $max)
    {
        parent::__construct('sales.cart_too_large', ['max' => $max]);
    }
}
