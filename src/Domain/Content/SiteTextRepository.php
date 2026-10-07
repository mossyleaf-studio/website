<?php

declare(strict_types=1);

namespace App\Domain\Content;

interface SiteTextRepository
{
    public function current(): SiteText;
}
