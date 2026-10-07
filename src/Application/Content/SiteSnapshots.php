<?php

declare(strict_types=1);

namespace App\Application\Content;

interface SiteSnapshots
{
    public function encode(SiteView $site): string;
}
