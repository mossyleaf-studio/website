<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class OpenedCheckout
{
    public function __construct(
        public string $id,
        public string $url,
    ) {
    }
}
