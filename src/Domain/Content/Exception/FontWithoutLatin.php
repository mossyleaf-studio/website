<?php

declare(strict_types=1);

namespace App\Domain\Content\Exception;

use App\Domain\Shared\Exception\DomainException;

final class FontWithoutLatin extends DomainException
{
    public function __construct(string $family)
    {
        parent::__construct('content.font_without_latin', ['family' => $family]);
    }
}
