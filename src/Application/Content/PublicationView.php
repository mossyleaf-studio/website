<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\PublishedSite;

final readonly class PublicationView
{
    private function __construct(
        public ?string $page,
        public ?string $publishedAt,
        public ?string $publishedBy,
        public bool $pendingChanges,
        public bool $open,
    ) {
    }

    public static function of(?PublishedSite $site, string $draft, string $shopDraft): self
    {
        if (null === $site) {
            return new self(null, null, null, true, false);
        }

        return new self($site->page()->value, $site->publishedAt()->format(\DATE_ATOM), $site->publishedBy(), $site->content() !== $draft || $site->shopContent() !== $shopDraft, $site->opensShop());
    }
}
