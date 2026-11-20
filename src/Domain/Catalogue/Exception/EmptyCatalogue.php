<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Exception;

use App\Domain\Shared\Exception\DomainException;

final class EmptyCatalogue extends DomainException
{
    public function __construct()
    {
        parent::__construct('catalogue.empty');
    }
}
