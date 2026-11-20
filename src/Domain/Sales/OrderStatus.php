<?php

declare(strict_types=1);

namespace App\Domain\Sales;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function allows(self $next): bool
    {
        return \in_array($next, match ($this) {
            self::Pending => [self::Paid, self::Failed, self::Expired],
            self::Failed, self::Expired => [self::Paid],
            self::Paid => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Cancelled],
            self::Cancelled => [],
        }, true);
    }
}
