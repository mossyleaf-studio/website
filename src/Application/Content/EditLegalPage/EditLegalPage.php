<?php

declare(strict_types=1);

namespace App\Application\Content\EditLegalPage;

use App\Domain\Content\LegalKind;

final readonly class EditLegalPage
{
    public function __construct(
        public LegalKind $kind,
        public string $text,
        public string $textFr,
    ) {
    }
}
