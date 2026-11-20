<?php

declare(strict_types=1);

namespace App\Application\Catalogue\UploadProductPhoto;

use App\Application\Catalogue\ProductView;
use App\Application\Content\ArtworkStorage;
use App\Application\Content\ImageResizer;
use App\Application\Transaction;
use App\Domain\Catalogue\PhotoFile;
use App\Domain\Catalogue\ProductRepository;
use Symfony\Component\Uid\Ulid;

final readonly class UploadProductPhotoHandler
{
    public const int THUMB_SIDE = 600;

    public function __construct(
        private ProductRepository $products,
        private ImageResizer $resizer,
        private ArtworkStorage $storage,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UploadProductPhoto $command): ProductView
    {
        $product = $this->products->get($command->productId);
        $full = $this->resizer->resize($command->path);
        $thumb = $this->resizer->resize($command->path, self::THUMB_SIDE);
        $id = new Ulid();

        $product->addPhoto(
            $id,
            new PhotoFile($this->storage->store($id, $full), $full->width, $full->height),
            new PhotoFile($this->storage->store(new Ulid(), $thumb), $thumb->width, $thumb->height),
        );
        $this->transaction->commit();

        return ProductView::of($product);
    }
}
