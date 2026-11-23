<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Storage;

use App\Application\Content\ResizedImage;
use App\Infrastructure\Storage\GdFaviconPainter;
use App\Infrastructure\Storage\LocalArtworkStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Ulid;

final class GdFaviconPainterTest extends TestCase
{
    private const int CREAM = 0xFFFDF4;
    private const int RED = 0xD02020;

    private string $directory;
    private LocalArtworkStorage $storage;
    private ArrayAdapter $cache;
    private GdFaviconPainter $painter;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/favicon-'.bin2hex(random_bytes(6));
        $this->storage = new LocalArtworkStorage($this->directory);
        $this->cache = new ArrayAdapter();
        $this->painter = new GdFaviconPainter($this->storage, $this->cache);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testAWideTransparentLogoSitsCentredOnAnOpaqueCreamSquare(): void
    {
        $png = $this->painter->paint($this->storeWideLogo());

        self::assertIsString($png);
        $image = imagecreatefromstring($png);
        self::assertInstanceOf(\GdImage::class, $image);
        self::assertSame([180, 180], [imagesx($image), imagesy($image)]);
        foreach ([[0, 0], [179, 0], [0, 179], [179, 179], [90, 30], [90, 150], [5, 90]] as [$x, $y]) {
            self::assertSame(self::CREAM, self::rgb($image, $x, $y), "{$x},{$y}");
        }
        self::assertSame(self::RED, self::rgb($image, 90, 90));
        self::assertSame(self::RED, self::rgb($image, 30, 80));
        self::assertSame(self::CREAM, self::rgb($image, 30, 60));
        for ($x = 0; $x < 180; ++$x) {
            for ($y = 0; $y < 180; ++$y) {
                self::assertSame(0, (imagecolorat($image, $x, $y) >> 24) & 0x7F, "{$x},{$y}");
            }
        }
    }

    public function testTheLogoIsPaintedOnceThenServedFromTheCache(): void
    {
        $file = $this->storeWideLogo();
        $first = $this->painter->paint($file);
        $this->storage->delete($file);

        self::assertIsString($first);
        self::assertSame($first, $this->painter->paint($file));
    }

    public function testAPictureCachedByAnEarlierDrawingIsNotServed(): void
    {
        $file = $this->storeWideLogo();
        $this->cache->get('favicon_'.hash('xxh128', $file), static fn (): string => 'an older drawing');

        $png = $this->painter->paint($file);

        self::assertIsString($png);
        self::assertNotSame('an older drawing', $png);
        self::assertNotFalse(imagecreatefromstring($png));
    }

    public function testAMissingOrUnreadableFileGivesNoPicture(): void
    {
        $broken = $this->storage->store(new Ulid(), new ResizedImage('not an image', 10, 10));

        self::assertNull($this->painter->paint($broken));
        self::assertNull($this->painter->paint(new Ulid()->toBase32().'.webp'));
        self::assertNull($this->painter->paint('../../etc/passwd'));
    }

    private function storeWideLogo(): string
    {
        $logo = imagecreatetruecolor(300, 100);
        self::assertInstanceOf(\GdImage::class, $logo);
        imagealphablending($logo, false);
        imagesavealpha($logo, true);
        imagefilledrectangle($logo, 0, 0, 299, 99, (int) imagecolorallocatealpha($logo, 0, 0, 0, 127));
        imagefilledrectangle($logo, 0, 25, 299, 74, (int) imagecolorallocate($logo, 0xD0, 0x20, 0x20));
        ob_start();
        imagewebp($logo, null, \IMG_WEBP_LOSSLESS);
        $bytes = (string) ob_get_clean();

        return $this->storage->store(new Ulid(), new ResizedImage($bytes, 300, 100));
    }

    private static function rgb(\GdImage $image, int $x, int $y): int
    {
        return imagecolorat($image, $x, $y) & 0xFFFFFF;
    }
}
