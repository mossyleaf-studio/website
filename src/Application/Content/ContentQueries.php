<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\ArtworkRepository;
use App\Domain\Content\LinkRepository;
use App\Domain\Content\SiteTextRepository;

final readonly class ContentQueries
{
    public function __construct(
        private SiteTextRepository $texts,
        private LinkRepository $links,
        private ArtworkRepository $artworks,
    ) {
    }

    public function site(): SiteView
    {
        return SiteView::of($this->texts->current(), $this->links->all(), $this->artworks->all());
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
