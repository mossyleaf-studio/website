<?php

declare(strict_types=1);

namespace App\Application\Content\DeleteArtwork;

use App\Application\Content\ArtworkStorage;
use App\Application\Transaction;
use App\Domain\Content\ArtworkRepository;

final readonly class DeleteArtworkHandler
{
    public function __construct(
        private ArtworkRepository $artworks,
        private ArtworkStorage $storage,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteArtwork $command): void
    {
        $artwork = $this->artworks->get($command->id);
        $this->artworks->remove($artwork);
        $this->transaction->commit();
        $this->storage->delete($artwork->file());
    }
}
