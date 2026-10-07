<?php

declare(strict_types=1);

namespace App\Application\Content\UploadLogo;

use App\Application\Content\ArtworkStorage;
use App\Application\Content\ImageResizer;
use App\Application\Content\MediaSweeper;
use App\Application\Content\SiteTextView;
use App\Application\Transaction;
use App\Domain\Content\Logo;
use App\Domain\Content\SiteTextRepository;
use Symfony\Component\Uid\Ulid;

final readonly class UploadLogoHandler
{
    public const int MAX_SIDE = 800;

    public function __construct(
        private SiteTextRepository $texts,
        private ImageResizer $resizer,
        private ArtworkStorage $storage,
        private MediaSweeper $media,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UploadLogo $command): SiteTextView
    {
        $image = $this->resizer->resize($command->path, self::MAX_SIDE);
        $file = $this->storage->store(new Ulid(), $image);

        $text = $this->texts->current();
        $text->useLogo(new Logo($file, $image->width, $image->height));
        $this->texts->save($text);
        $this->transaction->commit();
        $this->media->sweep();

        return SiteTextView::of($text);
    }
}
