<?php

declare(strict_types=1);

namespace App\Application\Content\ReorderArtworks;

final readonly class ReorderArtworks
{
    /** @param list<string> $ids */
    public function __construct(public array $ids)
    {
    }
}
