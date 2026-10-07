<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Content;

use App\Domain\Content\Exception\OrderMismatch;
use App\Domain\Content\Link;
use App\Domain\Content\Ordering;
use App\Domain\Content\TapeTone;
use PHPUnit\Framework\TestCase;

final class OrderingTest extends TestCase
{
    public function testPositionsFollowTheGivenOrder(): void
    {
        [$etsy, $instagram, $kofi] = self::links();

        Ordering::apply([$etsy, $instagram, $kofi], [(string) $kofi->id(), $etsy->id()->toRfc4122(), (string) $instagram->id()]);

        self::assertSame([1, 2, 0], [$etsy->position(), $instagram->position(), $kofi->position()]);
    }

    public function testAMissingDuplicatedOrUnknownIdIsRefused(): void
    {
        [$etsy, $instagram] = self::links();

        foreach ([[(string) $etsy->id()], [(string) $etsy->id(), (string) $etsy->id()], [(string) $etsy->id(), 'unknown']] as $ids) {
            try {
                Ordering::apply([$etsy, $instagram], $ids);
                self::fail('The order should have been refused.');
            } catch (OrderMismatch) {
                self::assertSame([0, 1], [$etsy->position(), $instagram->position()]);
            }
        }
    }

    /**
     * @return list<Link>
     */
    private static function links(): array
    {
        return [
            Link::create('Etsy', 'Shop', 'https://www.etsy.com/shop/mossyleafstudio', TapeTone::Leaf, 0),
            Link::create('Instagram', 'Drawings', 'https://www.instagram.com/mossyleaf.studio/', TapeTone::Blossom, 1),
            Link::create('Ko-fi', 'Coffee', 'https://ko-fi.com/mossyleaf', TapeTone::Leaf, 2),
        ];
    }
}
