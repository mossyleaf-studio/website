<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\ArtworkRepository;
use App\Domain\Content\LinkRepository;
use App\Domain\Content\PublicPage;
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
    ) {
    }

    public function site(): SiteView
    {
        return SiteView::of($this->texts->current(), $this->links->all(), $this->artworks->all());
    }

    public function draft(PublicPage $page): PublicSnapshot
    {
        return new PublicSnapshot($page, $this->snapshots->encode($this->site()));
    }

    public function public(): PublicSnapshot
    {
        $site = $this->published->current();

        return null === $site ? $this->draft(PublicPage::Note) : new PublicSnapshot($site->page(), $site->content());
    }

    public function publication(): PublicationView
    {
        return PublicationView::of($this->published->current(), $this->snapshots->encode($this->site()));
    }

    public function texts(): SiteTextView
    {
        return SiteTextView::of($this->texts->current());
    }

    /** @return list<LinkView> */
    public function links(): array
    {
        return LinkView::list($this->links->all());
    }

    /** @return list<ArtworkView> */
    public function artworks(): array
    {
        return ArtworkView::list($this->artworks->all());
    }
}
