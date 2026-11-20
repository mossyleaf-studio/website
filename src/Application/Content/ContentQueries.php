<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\ArtworkRepository;
use App\Domain\Content\LegalPageRepository;
use App\Domain\Content\LinkRepository;
use App\Domain\Content\PublishedSiteRepository;
use App\Domain\Content\SiteTextRepository;

final readonly class ContentQueries
{
    public function __construct(
        private SiteTextRepository $texts,
        private LinkRepository $links,
        private ArtworkRepository $artworks,
        private PublishedSiteRepository $published,
        private SiteSnapshots $snapshots,
        private LegalPageRepository $legalPages,
    ) {
    }

    public function site(): SiteView
    {
        return SiteView::of($this->texts->current(), $this->links->all(), $this->artworks->all());
    }

    public function draft(): PublicSnapshot
    {
        return new PublicSnapshot($this->snapshots->encode($this->site()));
    }

    public function public(): PublicSnapshot
    {
        $site = $this->published->current();

        return null === $site ? $this->draft() : new PublicSnapshot($site->content(), $site->publishedAt());
    }

    public function shopIsOpen(): bool
    {
        return $this->published->current()?->opensShop() ?? false;
    }

    public function shop(bool $draft): ShopContentView
    {
        if ($draft) {
            return ShopContentView::of($this->texts->current(), $this->links->all(), $this->artworks->all(), $this->legalPages->all());
        }
        $frozen = $this->published->current()?->shopContent();

        return null === $frozen ? new ShopContentView() : $this->snapshots->decodeShop($frozen);
    }

    public function publication(): PublicationView
    {
        return PublicationView::of($this->published->current(), $this->snapshots->encode($this->site()), $this->snapshots->encodeShop($this->shop(true)));
    }

    public function texts(): SiteTextView
    {
        return SiteTextView::of($this->texts->current());
    }

    /** @return list<DraftLinkView> */
    public function links(): array
    {
        return DraftLinkView::list($this->links->all());
    }

    /** @return list<DraftArtworkView> */
    public function artworks(): array
    {
        return DraftArtworkView::list($this->artworks->all());
    }

    /** @return list<LegalPageView> */
    public function legalPages(): array
    {
        return array_map(LegalPageView::of(...), $this->legalPages->all());
    }
}
