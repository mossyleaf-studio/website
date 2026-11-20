<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Symfony\Component\Uid\Ulid;

interface Orderable
{
    public function id(): Ulid;

    public function moveTo(int $position): void;
}
