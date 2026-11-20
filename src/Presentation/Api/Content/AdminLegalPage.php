<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\LegalPageView;
use App\Domain\Shared\Locale;
use App\Presentation\Web\Shop\ShopUrls;

final readonly class AdminLegalPage
{
    /** @param array<string, string> $preview */
    private function __construct(
        public string $kind,
        public string $text,
        public string $textFr,
        public ?string $updatedAt,
        public array $preview,
    ) {
    }

    public static function of(LegalPageView $page, ShopUrls $urls): self
    {
        $preview = [];
        foreach (Locale::cases() as $locale) {
            $preview[$locale->value] = $urls->to('shop_legal', $locale, true, ['kind' => $page->kind]);
        }

        return new self($page->kind, $page->text, $page->textFr, $page->updatedAt, $preview);
    }
}
