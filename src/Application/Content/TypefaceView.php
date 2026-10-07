<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Typeface;

final readonly class TypefaceView
{
    public const string URL_PREFIX = '/media/fonts/';

    private function __construct(
        public string $family,
        public int $weight,
        public string $stylesheet,
    ) {
    }

    public static function of(?Typeface $typeface): ?self
    {
        return null === $typeface ? null : new self($typeface->family, $typeface->weight, self::URL_PREFIX.$typeface->file);
    }
}
