<?php

declare(strict_types=1);

namespace App\Application\Content\DeleteArtwork;

use App\Application\Content\MediaSweeper;
use App\Application\Transaction;
use App\Domain\Content\ArtworkRepository;

final readonly class DeleteArtworkHandler
{
    public function __construct(
        private ArtworkRepository $artworks,
        private MediaSweeper $media,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteArtwork $command): void
    {
        $this->artworks->remove($this->artworks->get($command->id));
        $this->transaction->commit();
        $this->media->sweep();
    }
}
