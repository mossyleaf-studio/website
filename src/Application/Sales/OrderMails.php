<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Order;

interface OrderMails
{
    public function confirm(Order $order): void;

    public function notify(Order $order): void;
}
