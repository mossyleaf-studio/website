<?php

declare(strict_types=1);

namespace App\Application\Sales\DeleteTestOrder;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteTestOrder
{
    public function __construct(public Ulid $id)
    {
    }
}
