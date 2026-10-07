<?php

declare(strict_types=1);

namespace App\Application\Content\UnfeatureArtwork;

use App\Application\Content\ArtworkView;
use App\Application\Transaction;
use App\Domain\Content\ArtworkRepository;

final readonly class UnfeatureArtworkHandler
{
    public function __construct(
        private ArtworkRepository $artworks,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UnfeatureArtwork $command): ArtworkView
    {
        $artwork = $this->artworks->get($command->id);
        $artwork->unfeature();
        $this->transaction->commit();

        return ArtworkView::of($artwork);
    }
}
