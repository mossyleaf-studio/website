<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Sales\OrderStatus;
use App\Domain\Shared\Exception\DomainException;

final class OrderMoveRefused extends DomainException
{
    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        parent::__construct('sales.order_move_refused', ['from' => $from->value, 'to' => $to->value]);
    }
}
