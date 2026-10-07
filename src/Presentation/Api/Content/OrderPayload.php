<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class OrderPayload
{
    /** @param list<string> $ids */
    public function __construct(
        #[Assert\All([new Assert\Ulid(message: 'id.invalid')])]
        public array $ids = [],
    ) {
    }
}
