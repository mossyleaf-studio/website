<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Artwork;
use App\Domain\Content\ArtworkRepository;
use App\Domain\Content\FontRole;
use App\Domain\Content\PublishedSiteRepository;
use App\Domain\Content\SiteTextRepository;

final readonly class MediaSweeper
{
    public function __construct(
        private ArtworkRepository $artworks,
        private SiteTextRepository $texts,
        private PublishedSiteRepository $published,
        private ArtworkStorage $storage,
        private FontStorage $fonts,
    ) {
    }

    /** @return list<string> */
    public function draftFiles(): array
    {
        $text = $this->texts->current();

        return array_values(array_filter([
            ...array_map(static fn (Artwork $artwork): string => $artwork->file(), $this->artworks->all()),
            $text->logo()?->file,
            ...array_map(static fn (FontRole $role): ?string => $text->font($role)?->file, FontRole::cases()),
        ], static fn (?string $file): bool => null !== $file));
    }

    public function sweep(): void
    {
        $kept = [...$this->draftFiles(), ...($this->published->current()?->files() ?? [])];

        foreach (array_diff($this->storage->files(), $kept) as $file) {
            $this->storage->delete($file);
        }
        foreach (array_diff($this->fonts->files(), $kept) as $file) {
            $this->fonts->delete($file);
        }
    }
}
