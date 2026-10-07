<?php

declare(strict_types=1);

namespace App\Application\Content\DeleteArtwork;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteArtwork
{
    public function __construct(public Ulid $id)
    {
    }
}
