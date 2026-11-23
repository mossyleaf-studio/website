<?php

declare(strict_types=1);

namespace App\Application\Content;

interface FaviconPainter
{
    public function paint(string $file): ?string;
}
