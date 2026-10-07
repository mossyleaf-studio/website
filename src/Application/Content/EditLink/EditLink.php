<?php

declare(strict_types=1);

namespace App\Application\Content\EditLink;

use App\Domain\Content\TapeTone;
use Symfony\Component\Uid\Ulid;

final readonly class EditLink
{
    public function __construct(
        public Ulid $id,
        public string $title,
        public string $description,
        public string $url,
        public TapeTone $tape,
    ) {
    }
}
