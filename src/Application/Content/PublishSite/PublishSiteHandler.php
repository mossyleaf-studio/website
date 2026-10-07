<?php

declare(strict_types=1);

namespace App\Application\Content\PublishSite;

use App\Application\Content\ContentQueries;
use App\Application\Content\MediaSweeper;
use App\Application\Content\PublicationView;
use App\Application\Content\SiteSnapshots;
use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Content\PublishedSite;
use App\Domain\Content\PublishedSiteRepository;
use Psr\Clock\ClockInterface;

final readonly class PublishSiteHandler
{
    public function __construct(
        private ContentQueries $queries,
        private SiteSnapshots $snapshots,
        private PublishedSiteRepository $published,
        private MediaSweeper $media,
        private CurrentUser $currentUser,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(PublishSite $command): PublicationView
    {
        $content = $this->snapshots->encode($this->queries->site());
        $files = $this->media->draftFiles();
        $by = $this->currentUser->get()->displayName();
        $now = $this->clock->now();

        $site = $this->published->current();
        if (null === $site) {
            $this->published->add(PublishedSite::first($content, $files, $command->page, $by, $now));
        } else {
            $site->replace($content, $files, $command->page, $by, $now);
        }
        $this->transaction->commit();
        $this->media->sweep();

        return $this->queries->publication();
    }
}
