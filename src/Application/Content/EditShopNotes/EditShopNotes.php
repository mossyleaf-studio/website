<?php

declare(strict_types=1);

namespace App\Application\Content\EditShopNotes;

final readonly class EditShopNotes
{
    public function __construct(
        public string $announcement,
        public string $announcementFr,
        public string $priceNote,
        public string $priceNoteFr,
    ) {
    }
}
