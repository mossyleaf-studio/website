<?php

declare(strict_types=1);

namespace App\Domain\Content;

interface PublishedSiteRepository
{
    public function current(): ?PublishedSite;

    public function add(PublishedSite $site): void;
}
