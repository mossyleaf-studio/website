<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

final class EmptyText extends DomainException
{
    public function __construct(string $field)
    {
        parent::__construct('content.empty_text', ['field' => $field]);
    }
}
