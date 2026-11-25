<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Storage;

use App\Infrastructure\Storage\GdImageResizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GdImageResizerTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'resize-');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    /** @return iterable<string, array{\Closure(string): bool}> */
    public static function uploads(): iterable
    {
        yield 'jpeg' => [static fn (string $file): bool => imagejpeg(self::redPicture(true), $file)];
        yield 'palette png' => [static fn (string $file): bool => imagepng(self::redPicture(false), $file)];
        yield 'webp' => [static fn (string $file): bool => imagewebp(self::redPicture(true), $file)];
        yield 'avif' => [static fn (string $file): bool => imageavif(self::redPicture(true), $file)];
    }

    /** @param \Closure(string): bool $write */
    #[DataProvider('uploads')]
    public function testEverySupportedUploadComesOutAsAWebpWithinTheMaximumSide(\Closure $write): void
    {
        $write($this->file);

        $resized = new GdImageResizer()->resize($this->file, 40);

        self::assertSame([40, 20], [$resized->width, $resized->height]);
        self::assertSame(\IMAGETYPE_WEBP, getimagesizefromstring($resized->bytes)[2] ?? null);
        $image = imagecreatefromstring($resized->bytes);
        self::assertInstanceOf(\GdImage::class, $image);
        self::assertGreaterThan(0xB0, (imagecolorat($image, 20, 10) >> 16) & 0xFF);
    }

    private static function redPicture(bool $trueColor): \GdImage
    {
        $picture = $trueColor ? imagecreatetruecolor(80, 40) : imagecreate(80, 40);
        self::assertInstanceOf(\GdImage::class, $picture);
        imagefilledrectangle($picture, 0, 0, 79, 39, (int) imagecolorallocate($picture, 0xD0, 0x20, 0x20));

        return $picture;
    }
}
