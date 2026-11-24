<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Content;

use App\Domain\Content\PublicPage;
use App\Domain\Content\PublishedSite;
use PHPUnit\Framework\TestCase;

final class PublishedSiteTest extends TestCase
{
    public function testTheShopIsOpenExactlyWhenTheFullPageIsPublishedWithItsShopContent(): void
    {
        $now = new \DateTimeImmutable('2026-11-20 10:00:00');

        self::assertTrue(PublishedSite::first('{}', '{}', [], PublicPage::Full, 'lou', $now)->opensShop());
        self::assertFalse(PublishedSite::first('{}', '{}', [], PublicPage::Note, 'lou', $now)->opensShop());
    }

    public function testAFullPageFrozenWithoutShopContentDoesNotOpenTheShop(): void
    {
        $site = PublishedSite::first('{}', '{}', [], PublicPage::Full, 'lou', new \DateTimeImmutable());
        new \ReflectionProperty($site, 'shopContent')->setValue($site, null);

        self::assertFalse($site->opensShop());
    }

    public function testPublishingTheNoteClosesTheShopAndPublishingTheFullPageOpensItAgain(): void
    {
        $now = new \DateTimeImmutable();
        $site = PublishedSite::first('{}', '{}', [], PublicPage::Full, 'lou', $now);

        $site->replace('{}', '{}', [], PublicPage::Note, 'lou', $now);
        self::assertFalse($site->opensShop());

        $site->replace('{}', '{}', [], PublicPage::Full, 'lou', $now);
        self::assertTrue($site->opensShop());
    }
}
