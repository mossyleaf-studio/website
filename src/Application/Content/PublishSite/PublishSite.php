<?php

declare(strict_types=1);

namespace App\Application\Content\PublishSite;

use App\Domain\Content\PublicPage;

final readonly class PublishSite
{
    public function __construct(public PublicPage $page)
    {
    }
}
