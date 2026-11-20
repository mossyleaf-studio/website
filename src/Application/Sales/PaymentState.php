<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum PaymentState: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
}
