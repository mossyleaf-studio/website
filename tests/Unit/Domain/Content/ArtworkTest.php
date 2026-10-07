<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Content;

use App\Domain\Content\Artwork;
use App\Domain\Content\Exception\TextTooLong;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ArtworkTest extends TestCase
{
    public function testAnArtworkStartsInTheGalleryWithItsDescription(): void
    {
        $artwork = Artwork::upload(new Ulid(), 'file.webp', 1600, 1200, ' A fern ', 0, new \DateTimeImmutable());

        self::assertSame(['A fern', false], [$artwork->alt(), $artwork->isFeatured()]);
    }

    public function testFeaturingCanBeUndone(): void
    {
        $artwork = Artwork::upload(new Ulid(), 'file.webp', 10, 10, '', 0, new \DateTimeImmutable());

        $artwork->feature();
        self::assertTrue($artwork->isFeatured());
        $artwork->unfeature();
        self::assertFalse($artwork->isFeatured());
    }

    public function testATooLongDescriptionIsRefused(): void
    {
        $this->expectException(TextTooLong::class);

        Artwork::upload(new Ulid(), 'file.webp', 10, 10, str_repeat('a', Artwork::MAX_ALT + 1), 0, new \DateTimeImmutable());
    }
}
