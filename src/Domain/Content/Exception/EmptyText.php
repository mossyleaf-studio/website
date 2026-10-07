<?php

declare(strict_types=1);

namespace App\Domain\Content\Exception;

use App\Domain\Shared\Exception\DomainException;

final class EmptyText extends DomainException
{
    public function __construct(string $field)
    {
        parent::__construct('content.empty_text', ['field' => $field]);
    }
}
