<?php

declare(strict_types=1);

namespace App\Domain\Content;

enum FontRole: string
{
    case Heading = 'heading';
    case Body = 'body';

    public function preferredWeight(): int
    {
        return match ($this) {
            self::Heading => 700,
            self::Body => 400,
        };
    }
}
