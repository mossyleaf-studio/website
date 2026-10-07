<?php

declare(strict_types=1);

namespace App\Application\Content\DescribeArtwork;

use App\Application\Content\ArtworkView;
use App\Application\Transaction;
use App\Domain\Content\ArtworkRepository;

final readonly class DescribeArtworkHandler
{
    public function __construct(
        private ArtworkRepository $artworks,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DescribeArtwork $command): ArtworkView
    {
        $artwork = $this->artworks->get($command->id);
        $artwork->describe($command->alt);
        $this->transaction->commit();

        return ArtworkView::of($artwork);
    }
}
