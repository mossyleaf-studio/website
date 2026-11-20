<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Exception;

use App\Domain\Shared\Exception\DomainException;

final class NegativeAmount extends DomainException
{
    public function __construct(string $field)
    {
        parent::__construct('catalogue.negative_amount', ['field' => $field]);
    }
}
