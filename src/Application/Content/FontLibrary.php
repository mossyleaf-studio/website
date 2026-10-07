<?php

declare(strict_types=1);

namespace App\Application\Content;

interface FontLibrary
{
    /** @return list<string> */
    public function families(): array;

    public function download(string $family, int $preferredWeight): DownloadedFont;
}
