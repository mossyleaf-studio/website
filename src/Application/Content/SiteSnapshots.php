<?php

declare(strict_types=1);

namespace App\Application\Content;

interface SiteSnapshots
{
    public function encode(SiteView $site): string;

    public function encodeShop(ShopContentView $shop): string;

    public function decodeShop(string $content): ShopContentView;
}
