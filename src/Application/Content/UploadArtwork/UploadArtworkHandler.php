<?php

declare(strict_types=1);

namespace App\Application\Content\UploadArtwork;

use App\Application\Content\ArtworkStorage;
use App\Application\Content\ArtworkView;
use App\Application\Content\ImageResizer;
use App\Application\Transaction;
use App\Domain\Content\Artwork;
use App\Domain\Content\ArtworkRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class UploadArtworkHandler
{
    public function __construct(
        private ArtworkRepository $artworks,
        private ImageResizer $resizer,
        private ArtworkStorage $storage,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UploadArtwork $command): ArtworkView
    {
        $image = $this->resizer->resize($command->path);
        $id = new Ulid();
        $file = $this->storage->store($id, $image);

        $artwork = Artwork::upload($id, $file, $image->width, $image->height, $command->alt, $this->artworks->nextPosition(), $this->clock->now());
        $this->artworks->add($artwork);
        $this->transaction->commit();

        return ArtworkView::of($artwork);
    }
}
