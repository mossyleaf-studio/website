<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReplaceCatalogue;

final readonly class ReplaceCatalogue
{
    /**
     * @param list<PushedProduct> $products
     */
    public function __construct(public array $products)
    {
    }
}
