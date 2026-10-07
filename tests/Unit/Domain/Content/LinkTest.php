<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Content;

use App\Domain\Content\Exception\InvalidLinkUrl;
use App\Domain\Content\Link;
use App\Domain\Content\TapeTone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LinkTest extends TestCase
{
    public function testALinkKeepsItsTextsAndTape(): void
    {
        $link = Link::create(' Shop on Etsy ', 'Prints', ' https://www.etsy.com/shop/mossyleafstudio ', TapeTone::Blossom, 3);

        self::assertSame(['Shop on Etsy', 'https://www.etsy.com/shop/mossyleafstudio', TapeTone::Blossom, 3], [$link->title(), $link->url(), $link->tape(), $link->position()]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedUrls(): iterable
    {
        yield 'plain http' => ['http://example.com'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'no scheme' => ['www.etsy.com'];
        yield 'empty' => [''];
    }

    #[DataProvider('refusedUrls')]
    public function testOnlyHttpsAddressesAreAccepted(string $url): void
    {
        $this->expectException(InvalidLinkUrl::class);

        Link::create('Shop', 'Prints', $url, TapeTone::Leaf, 0);
    }
}
