<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Domain\Content\PublicPage;

final readonly class PublicationPayload
{
    public function __construct(public PublicPage $page = PublicPage::Full)
    {
    }
}
