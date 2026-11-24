<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Content;

use App\Domain\Content\Artwork;
use App\Domain\Shared\Exception\TextTooLong;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ArtworkTest extends TestCase
{
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

    public function testTheFrenchDescriptionIsOptionalAndStaysWhenLeftOut(): void
    {
        $artwork = Artwork::upload(new Ulid(), 'file.webp', 10, 10, 'A fern', 0, new \DateTimeImmutable());
        self::assertSame('', $artwork->altFr());

        $artwork->describe('A fern', ' Une fougère ');
        $artwork->describe('A fern unrolling');

        self::assertSame(['A fern unrolling', 'Une fougère'], [$artwork->alt(), $artwork->altFr()]);
    }

    public function testATooLongFrenchDescriptionIsRefused(): void
    {
        $this->expectException(TextTooLong::class);

        Artwork::upload(new Ulid(), 'file.webp', 10, 10, '', 0, new \DateTimeImmutable())->describe('', str_repeat('é', Artwork::MAX_ALT + 1));
    }
}
