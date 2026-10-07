<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Content\Exception\UnknownFont;

final readonly class Typeface
{
    public const int MAX_FAMILY = 60;
    public const string FAMILY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9 ]*$/';

    public function __construct(
        public string $family,
        public string $file,
        public int $weight,
    ) {
        self::family($family);
    }

    public static function family(string $family): string
    {
        $family = trim(preg_replace('/\s+/', ' ', $family) ?? '');
        if (mb_strlen($family) > self::MAX_FAMILY || 1 !== preg_match(self::FAMILY_PATTERN, $family)) {
            throw new UnknownFont($family);
        }

        return $family;
    }
}
