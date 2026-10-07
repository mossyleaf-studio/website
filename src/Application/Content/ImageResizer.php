<?php

declare(strict_types=1);

namespace App\Application\Content;

interface ImageResizer
{
    public const int MAX_SIDE = 1600;

    public function resize(string $path): ResizedImage;
}
