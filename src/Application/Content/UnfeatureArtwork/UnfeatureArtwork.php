<?php

declare(strict_types=1);

namespace App\Application\Content\UnfeatureArtwork;

use Symfony\Component\Uid\Ulid;

final readonly class UnfeatureArtwork
{
    public function __construct(public Ulid $id)
    {
    }
}
