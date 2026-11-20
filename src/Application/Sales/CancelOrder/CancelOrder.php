<?php

declare(strict_types=1);

namespace App\Application\Sales\CancelOrder;

use Symfony\Component\Uid\Ulid;

final readonly class CancelOrder
{
    public function __construct(public Ulid $id)
    {
    }
}
