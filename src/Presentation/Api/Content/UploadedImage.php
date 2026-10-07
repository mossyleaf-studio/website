<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Domain\Content\Exception\ImageTooLarge;
use App\Domain\Content\Exception\UnsupportedImage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final class UploadedImage
{
    private const string FIELD = 'image';

    public static function pathIn(Request $request): string
    {
        $file = $request->files->get(self::FIELD);
        if ($file instanceof UploadedFile && \UPLOAD_ERR_INI_SIZE === $file->getError()) {
            throw new ImageTooLarge(ImageTooLarge::MAX_MEGABYTES);
        }
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new UnsupportedImage();
        }
        if ($file->getSize() > ImageTooLarge::MAX_MEGABYTES * 1024 * 1024) {
            throw new ImageTooLarge(ImageTooLarge::MAX_MEGABYTES);
        }

        return $file->getPathname();
    }
}
