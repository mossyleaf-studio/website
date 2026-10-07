<?php

declare(strict_types=1);

namespace App\Application\Content\FeatureArtwork;

use Symfony\Component\Uid\Ulid;

final readonly class FeatureArtwork
{
    public function __construct(public Ulid $id)
    {
    }
}
