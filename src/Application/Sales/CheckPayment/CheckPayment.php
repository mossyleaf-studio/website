<?php

declare(strict_types=1);

namespace App\Application\Sales\CheckPayment;

use Symfony\Component\Uid\Ulid;

final readonly class CheckPayment
{
    public function __construct(public Ulid $id)
    {
    }
}
