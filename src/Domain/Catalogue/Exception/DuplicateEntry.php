<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Exception;

use App\Domain\Shared\Exception\DomainException;

final class DuplicateEntry extends DomainException
{
    public function __construct(string $field, string $value)
    {
        parent::__construct('catalogue.duplicate_entry', ['field' => $field, 'value' => $value]);
    }
}
