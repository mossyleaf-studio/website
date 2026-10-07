<?php

declare(strict_types=1);

namespace App\Application\Content\FeatureArtwork;

use App\Application\Content\ArtworkView;
use App\Application\Transaction;
use App\Domain\Content\ArtworkRepository;

final readonly class FeatureArtworkHandler
{
    public function __construct(
        private ArtworkRepository $artworks,
        private Transaction $transaction,
    ) {
    }

    /** @return list<ArtworkView> */
    public function __invoke(FeatureArtwork $command): array
    {
        $artwork = $this->artworks->get($command->id);
        $this->artworks->featured()?->unfeature();
        $artwork->feature();
        $this->transaction->commit();

        return ArtworkView::list($this->artworks->all());
    }
}
