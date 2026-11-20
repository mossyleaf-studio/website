<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\LegalPage;

final readonly class LegalPageView
{
    private function __construct(
        public string $kind,
        public string $text,
        public string $textFr,
        public ?string $updatedAt,
    ) {
    }

    public static function of(LegalPage $page): self
    {
        return new self($page->kind()->value, $page->text()->en, $page->text()->fr, $page->updatedAt()?->format(\DATE_ATOM));
    }
}
