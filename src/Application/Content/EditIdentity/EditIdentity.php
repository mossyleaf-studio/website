<?php

declare(strict_types=1);

namespace App\Application\Content\EditIdentity;

final readonly class EditIdentity
{
    public function __construct(
        public string $studioName,
        public string $intro,
        public string $metaDescription,
        public string $searchTitle = '',
        public ?string $introFr = null,
        public ?string $metaDescriptionFr = null,
        public ?string $searchTitleFr = null,
    ) {
    }
}
