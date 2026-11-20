<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Shared\Exception\DomainException;

final class CartOutdated extends DomainException
{
    public function __construct()
    {
        parent::__construct('sales.cart_outdated');
    }
}
