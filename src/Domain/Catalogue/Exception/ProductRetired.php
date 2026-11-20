<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Exception;

use App\Domain\Shared\Exception\DomainException;

final class ProductRetired extends DomainException
{
    public function __construct(string $reference)
    {
        parent::__construct('catalogue.product_retired', ['reference' => $reference]);
    }
}
