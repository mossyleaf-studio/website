<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

final class TextTooLong extends DomainException
{
    public function __construct(string $field, int $max)
    {
        parent::__construct('content.text_too_long', ['field' => $field, 'max' => $max]);
    }
}
