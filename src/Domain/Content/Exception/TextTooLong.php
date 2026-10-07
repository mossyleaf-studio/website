<?php

declare(strict_types=1);

namespace App\Domain\Content\Exception;

use App\Domain\Shared\Exception\DomainException;

final class TextTooLong extends DomainException
{
    public function __construct(string $field, int $max)
    {
        parent::__construct('content.text_too_long', ['field' => $field, 'max' => $max]);
    }
}
