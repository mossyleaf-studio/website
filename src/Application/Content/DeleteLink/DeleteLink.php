<?php

declare(strict_types=1);

namespace App\Application\Content\DeleteLink;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteLink
{
    public function __construct(public Ulid $id)
    {
    }
}
