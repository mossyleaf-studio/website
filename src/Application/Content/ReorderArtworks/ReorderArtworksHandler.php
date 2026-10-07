<?php

declare(strict_types=1);

namespace App\Application\Content\ReorderArtworks;

use App\Application\Content\ArtworkView;
use App\Application\Transaction;
use App\Domain\Content\Artwork;
use App\Domain\Content\ArtworkRepository;
use App\Domain\Content\Ordering;

final readonly class ReorderArtworksHandler
{
    public function __construct(
        private ArtworkRepository $artworks,
        private Transaction $transaction,
    ) {
    }

    /** @return list<ArtworkView> */
    public function __invoke(ReorderArtworks $command): array
    {
        $artworks = $this->artworks->all();
        Ordering::apply($artworks, $command->ids);
        $this->transaction->commit();

        usort($artworks, static fn (Artwork $a, Artwork $b): int => $a->position() <=> $b->position());

        return ArtworkView::list($artworks);
    }
}
