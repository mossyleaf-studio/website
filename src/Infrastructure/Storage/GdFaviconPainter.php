<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Content\ArtworkStorage;
use App\Application\Content\FaviconPainter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class GdFaviconPainter implements FaviconPainter
{
    private const string DRAWING = 'cream-180-18';
    private const int SIDE = 180;
    private const int MARGIN = 18;
    private const int CREAM = 0xFFFDF4;
    private const int MAX_BYTES = 20_000_000;
    private const int MAX_PIXELS = 16_000_000;
    private const int RETRY_LATER = 300;

    public function __construct(
        private ArtworkStorage $storage,
        private CacheInterface $cache,
    ) {
    }

    public function paint(string $file): ?string
    {
        return $this->cache->get('favicon_'.hash('xxh128', self::DRAWING.'/'.$file), function (ItemInterface $item) use ($file): ?string {
            $png = $this->draw($file);
            if (null === $png) {
                $item->expiresAfter(self::RETRY_LATER);
            }

            return $png;
        });
    }

    private function draw(string $file): ?string
    {
        $logo = $this->open($file);
        $canvas = imagecreatetruecolor(self::SIDE, self::SIDE);
        if (null === $logo || false === $canvas) {
            return null;
        }
        imagefilledrectangle($canvas, 0, 0, self::SIDE - 1, self::SIDE - 1, (int) imagecolorallocate($canvas, self::CREAM >> 16, (self::CREAM >> 8) & 0xFF, self::CREAM & 0xFF));
        imagealphablending($canvas, true);

        $width = imagesx($logo);
        $height = imagesy($logo);
        $box = self::SIDE - 2 * self::MARGIN;
        $scale = $box / max($width, $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        imagecopyresampled($canvas, $logo, intdiv(self::SIDE - $targetWidth, 2), intdiv(self::SIDE - $targetHeight, 2), 0, 0, $targetWidth, $targetHeight, $width, $height);
        unset($logo);

        ob_start();
        $written = imagepng($canvas, null, 9);
        $bytes = (string) ob_get_clean();

        return $written && '' !== $bytes ? $bytes : null;
    }

    private function open(string $file): ?\GdImage
    {
        $path = $this->storage->path($file);
        if (null === $path || (int) filesize($path) > self::MAX_BYTES) {
            return null;
        }
        $size = @getimagesize($path);
        if (false === $size || \IMAGETYPE_WEBP !== $size[2] || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_PIXELS) {
            return null;
        }
        $image = @imagecreatefromwebp($path);
        if (!$image instanceof \GdImage) {
            return null;
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }
}
