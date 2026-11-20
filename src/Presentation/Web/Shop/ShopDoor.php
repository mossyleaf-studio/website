<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

final readonly class ShopDoor
{
    public function admits(bool $preview): bool
    {
        return $preview;
    }
}
