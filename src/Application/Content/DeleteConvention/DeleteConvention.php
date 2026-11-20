<?php

declare(strict_types=1);

namespace App\Application\Content\DeleteConvention;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteConvention
{
    public function __construct(public Ulid $id)
    {
    }
}
