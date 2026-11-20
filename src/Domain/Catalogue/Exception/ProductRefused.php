<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Exception;

use App\Domain\Shared\Exception\DomainException;

final class ProductRefused extends DomainException
{
    public function __construct(string $reference, DomainException $reason)
    {
        parent::__construct('catalogue.product_refused', ['reference' => $reference] + $reason->parameters() + ['field' => '', 'max' => 0]);
    }
}
