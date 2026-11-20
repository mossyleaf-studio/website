<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\PublicationView;
use App\Domain\Shared\Locale;
use App\Presentation\Web\Shop\ShopUrls;

final readonly class AdminPublication
{
    /** @param array{shop: array<string, string>, about: array<string, string>, note: string} $preview */
    private function __construct(
        public ?string $page,
        public ?string $publishedAt,
        public ?string $publishedBy,
        public bool $pendingChanges,
        public bool $open,
        public array $preview,
    ) {
    }

    public static function of(PublicationView $publication, ShopUrls $urls): self
    {
        $shop = [];
        $about = [];
        foreach (Locale::cases() as $locale) {
            $shop[$locale->value] = $urls->to('shop_home', $locale, true);
            $about[$locale->value] = $urls->to('shop_about', $locale, true);
        }

        return new self($publication->page, $publication->publishedAt, $publication->publishedBy, $publication->pendingChanges, $publication->open, ['shop' => $shop, 'about' => $about, 'note' => $urls->note()]);
    }
}
