<?php

declare(strict_types=1);

namespace App\Domain\Content\Exception;

use App\Domain\Shared\Exception\DomainException;

final class ConventionEndsBeforeStart extends DomainException
{
    public function __construct()
    {
        parent::__construct('content.convention_ends_before_start');
    }
}
