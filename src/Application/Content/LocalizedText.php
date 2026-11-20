<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Shared\Locale;
use App\Domain\Shared\Translation;

final readonly class LocalizedText
{
    public function __construct(
        public string $en = '',
        public string $fr = '',
    ) {
    }

    public static function of(string $en, string $fr): self
    {
        $text = Translation::of($en, $fr);

        return new self($text->in(Locale::En), $text->in(Locale::Fr));
    }
}
