<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidSlug extends DomainException
{
    public function __construct(int $max)
    {
        parent::__construct('catalogue.invalid_slug', ['max' => $max]);
    }
}
