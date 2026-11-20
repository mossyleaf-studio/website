<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Exception;

use App\Domain\Shared\Exception\DomainException;

final class SlugTaken extends DomainException
{
    public function __construct(string $slug)
    {
        parent::__construct('catalogue.slug_taken', ['slug' => $slug]);
    }
}
