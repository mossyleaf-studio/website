<?php

declare(strict_types=1);

namespace App\Application\Content\ChooseFont;

use App\Application\Content\FontLibrary;
use App\Application\Content\FontStorage;
use App\Application\Content\MediaSweeper;
use App\Application\Content\SiteTextView;
use App\Application\Transaction;
use App\Domain\Content\SiteTextRepository;
use App\Domain\Content\Typeface;
use Symfony\Component\Uid\Ulid;

final readonly class ChooseFontHandler
{
    public function __construct(
        private SiteTextRepository $texts,
        private FontLibrary $library,
        private FontStorage $storage,
        private MediaSweeper $media,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ChooseFont $command): SiteTextView
    {
        $typeface = '' === trim($command->family) ? null : $this->download($command);

        $text = $this->texts->current();
        $text->useFont($command->role, $typeface);
        $this->texts->save($text);
        $this->transaction->commit();
        $this->media->sweep();

        return SiteTextView::of($text);
    }

    private function download(ChooseFont $command): Typeface
    {
        $font = $this->library->download(Typeface::family($command->family), $command->role->preferredWeight());
        $file = $this->storage->store(new Ulid(), $font);

        return new Typeface($font->family, $file, $font->weight);
    }
}
