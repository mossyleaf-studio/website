<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\LegalPage;

final readonly class ShopLegalPageView
{
    public function __construct(
        public string $kind,
        public LocalizedText $text,
        public ?string $updatedAt = null,
    ) {
    }

    public static function of(LegalPage $page): self
    {
        return new self($page->kind()->value, LocalizedText::of($page->text()->en, $page->text()->fr), $page->updatedAt()?->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d'));
    }
}
