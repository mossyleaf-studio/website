<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Artwork;
use App\Domain\Content\ArtworkRepository;
use App\Domain\Content\PublishedSiteRepository;
use App\Domain\Content\SiteTextRepository;

final readonly class MediaSweeper
{
    public function __construct(
        private ArtworkRepository $artworks,
        private SiteTextRepository $texts,
        private PublishedSiteRepository $published,
        private ArtworkStorage $storage,
    ) {
    }

    /** @return list<string> */
    public function draftFiles(): array
    {
        $files = array_map(static fn (Artwork $artwork): string => $artwork->file(), $this->artworks->all());
        $logo = $this->texts->current()->logo();

        return null === $logo ? $files : [...$files, $logo->file];
    }

    public function sweep(): void
    {
        $kept = [...$this->draftFiles(), ...($this->published->current()?->files() ?? [])];

        foreach (array_diff($this->storage->files(), $kept) as $file) {
            $this->storage->delete($file);
        }
    }
}
