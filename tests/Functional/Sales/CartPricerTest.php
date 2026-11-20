<?php

declare(strict_types=1);

namespace App\Tests\Functional\Sales;

use App\Application\Sales\CartLine;
use App\Application\Sales\CartPricer;
use App\Application\Sales\EditShippingZone\EditShippingZone;
use App\Application\Sales\EditShippingZone\EditShippingZoneHandler;
use App\Application\Sales\Quote;
use App\Application\Sales\QuoteIssue;
use App\Application\Sales\QuoteLine;
use App\Domain\Sales\Exception\CartTooLarge;
use App\Domain\Sales\Exception\UnknownCountry;
use App\Domain\Sales\Zone;
use App\Domain\Shared\Locale;
use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CartPricerTest extends WebTestCase
{
    use PushesCatalogue;
    use SignsInClient;

    public function testUnitPricesAndTitlesComeFromTheCatalogueInThePageLanguage(): void
    {
        self::stock();

        $quote = self::quote([['PRI-FOX-A4', 2], ['STI-MOTH', 1]], null, locale: Locale::Fr);

        self::assertSame(
            [['PRI-FOX-A4', 'Affiche renard', 'A4', 1500, 2, 3000, 'prints-fox', 10], ['STI-MOTH', 'Stickers Moth', null, 300, 1, 300, 'stickers-moth', 3]],
            self::lines($quote),
        );
        self::assertSame([], $quote->issues);
        self::assertSame([3300, 3300, 3], [$quote->goods, $quote->total, $quote->units]);
        self::assertSame('Fox print', self::quote([['PRI-FOX-A4', 1]], null)->lines[0]->title);
    }

    public function testTheSameSkuGivenTwiceIsMergedInTheOrderItCameFirst(): void
    {
        self::stock();

        $quote = self::quote([['STI-MOTH', 1], ['PRI-FOX-A4', 1], ['STI-MOTH', 1]], null);

        self::assertSame([['STI-MOTH', 2], ['PRI-FOX-A4', 1]], array_map(static fn (QuoteLine $line): array => [$line->sku, $line->quantity], $quote->lines));
        self::assertSame([], $quote->issues);
        self::assertSame(3, $quote->units);
    }

    public function testAQuantityAboveTenOrAboveWhatIsLeftIsReduced(): void
    {
        self::stock();

        $quote = self::quote([['PRI-FOX-A4', 12], ['STI-MOTH', 2], ['STI-MOTH', 2], ['PRI-WREN', 0]], null);

        self::assertSame([['PRI-FOX-A4', 10], ['STI-MOTH', 3]], array_map(static fn (QuoteLine $line): array => [$line->sku, $line->quantity], $quote->lines));
        self::assertSame([['reduced', 'PRI-FOX-A4', 'Fox print'], ['reduced', 'STI-MOTH', 'Stickers Moth'], ['removed', 'PRI-WREN', 'Prints Wren']], self::issues($quote));
        self::assertSame(15900, $quote->goods);
    }

    public function testAQuantityBelowOneCountsAsOne(): void
    {
        self::stock();

        self::assertSame(1, self::quote([['STI-MOTH', 0]], null)->lines[0]->quantity);
    }

    public function testWhatCannotBeSoldIsRemovedWithAnIssue(): void
    {
        self::stock();

        $quote = self::quote([['NOPE', 1], ['CAR-BEE', 1], ['PRI-OWL', 1], ['PRI-HARE', 1], ['PRI-WREN', 1], ['PRI-FOX-A5', 2], ['STI-MOTH', 1]], 'FR');

        self::assertSame([['STI-MOTH', 1]], array_map(static fn (QuoteLine $line): array => [$line->sku, $line->quantity], $quote->lines));
        self::assertSame(
            [['removed', 'NOPE', null], ['removed', 'CAR-BEE', null], ['removed', 'PRI-OWL', null], ['removed', 'PRI-HARE', null], ['removed', 'PRI-WREN', 'Prints Wren'], ['removed', 'PRI-FOX-A5', 'Fox print']],
            self::issues($quote),
        );
        self::assertSame([300, 1], [$quote->goods, $quote->units]);
    }

    public function testThePreviewKeepsAHiddenProduct(): void
    {
        self::stock();

        $quote = self::quote([['CAR-BEE', 1]], null, true);

        self::assertSame([['CAR-BEE', 'Cards Bee', null, 500, 1, 500, 'cards-bee', 4]], self::lines($quote));
        self::assertSame([], $quote->issues);
    }

    public function testACartHoldsThirtyLinesAtMost(): void
    {
        self::stock();
        $lines = array_map(static fn (int $number): array => ['SKU-'.$number, 1], range(1, 30));

        self::assertCount(30, self::quote($lines, null)->issues);
        self::assertCount(30, self::quote([...$lines, ['SKU-1', 1]], null)->issues);

        $this->expectException(CartTooLarge::class);
        self::quote([...$lines, ['SKU-31', 1]], null);
    }

    public function testAnEmptyCartIsPricedAtZero(): void
    {
        self::stock();
        self::zone('france', 650, null, true);

        $quote = self::quote([], 'FR');

        self::assertSame([[], [], 0, 0, 0], [$quote->lines, $quote->issues, $quote->goods, $quote->total, $quote->units]);
        self::assertSame(0, $quote->shipping?->price);
    }

    public function testShippingCostsThePriceOfTheZoneOfTheCountry(): void
    {
        self::stock();
        self::zone('france', 650, 6000, true);
        self::zone('eu', 1200, null, true);

        $france = self::quote([['PRI-FOX-A4', 2]], 'MC');
        self::assertSame(['france', 650, 6000, false], self::shipping($france));
        self::assertSame([true, 3000, 3650], [$france->deliverable, $france->goods, $france->total]);

        $germany = self::quote([['PRI-FOX-A4', 2]], 'DE');
        self::assertSame(['eu', 1200, null, false], self::shipping($germany));
        self::assertSame(4200, $germany->total);
    }

    public function testShippingIsFreeExactlyAtTheThresholdAndAbove(): void
    {
        self::stock();
        self::zone('france', 650, 6000, true);

        foreach ([[4, 6000], [5, 7500]] as [$quantity, $goods]) {
            $quote = self::quote([['PRI-FOX-A4', $quantity]], 'FR');

            self::assertSame(['france', 0, 6000, true], self::shipping($quote));
            self::assertSame([$goods, $goods], [$quote->goods, $quote->total]);
        }
    }

    public function testShippingIsNeverFreeWithoutAThreshold(): void
    {
        self::stock();
        self::zone('world', 1800, null, true);

        $quote = self::quote([['PRI-FOX-A4', 10]], 'JP');

        self::assertSame(['world', 1800, null, false], self::shipping($quote));
        self::assertSame(16800, $quote->total);
    }

    public function testWithoutACountryThereIsNoShipping(): void
    {
        self::stock();
        self::zone('france', 650, null, true);

        $quote = self::quote([['PRI-FOX-A4', 1]], null);

        self::assertNull($quote->shipping);
        self::assertFalse($quote->deliverable);
        self::assertSame(1500, $quote->total);
    }

    public function testACountryWhoseZoneIsOffCannotBeDelivered(): void
    {
        self::stock();
        self::zone('france', 650, null, true);
        self::zone('eu', 1200, null, false);

        $quote = self::quote([['PRI-FOX-A4', 1]], 'BE');

        self::assertFalse($quote->deliverable);
        self::assertSame('eu', $quote->shipping?->zone);
        self::assertSame([1500, 1500], [$quote->goods, $quote->total]);
        self::assertFalse(self::quote([['PRI-FOX-A4', 1]], 'US')->deliverable);
    }

    public function testAnUnknownCountryIsRefused(): void
    {
        self::stock();

        $this->expectException(UnknownCountry::class);

        self::quote([['PRI-FOX-A4', 1]], 'XX');
    }

    private static function stock(): void
    {
        $client = self::signedInClient();
        $products = [
            self::product(11, 'Prints', 'Fox', 1500, ['A4' => 12, 'A5' => 0]),
            self::product(12, 'Stickers', 'Moth', 300, 3),
            self::product(13, 'Cards', 'Bee', 500, 4),
            self::product(14, 'Prints', 'Hare', 0, 5),
            self::product(15, 'Prints', 'Owl', 1200, 5),
            self::product(16, 'Prints', 'Wren', 800, 0),
        ];
        $ids = self::push($products);
        foreach (['prints-fox', 'stickers-moth', 'prints-hare', 'prints-owl', 'prints-wren'] as $slug) {
            self::show($client, $ids[$slug]);
        }
        self::describe($client, $ids['prints-fox'], 'prints-fox', ['title' => 'Fox print', 'titleFr' => 'Affiche renard']);
        self::push([$products[0], $products[1], $products[2], $products[3], $products[5]]);
    }

    private static function zone(string $code, int $price, ?int $freeFrom, bool $enabled): void
    {
        self::getContainer()->get(EditShippingZoneHandler::class)(new EditShippingZone(Zone::from($code), $price, $freeFrom, $enabled));
    }

    /** @param list<array{string, int}> $lines */
    private static function quote(array $lines, ?string $country, bool $preview = false, Locale $locale = Locale::En): Quote
    {
        return self::getContainer()->get(CartPricer::class)->quote(
            array_map(static fn (array $line): CartLine => new CartLine($line[0], $line[1]), $lines),
            $country,
            $preview,
            $locale,
        );
    }

    /** @return list<array{string, string, ?string, int, int, int, string, int}> */
    private static function lines(Quote $quote): array
    {
        return array_map(static fn (QuoteLine $line): array => [$line->sku, $line->title, $line->variant, $line->unitPrice, $line->quantity, $line->lineTotal, $line->slug, $line->available], $quote->lines);
    }

    /** @return list<array{string, string, ?string}> */
    private static function issues(Quote $quote): array
    {
        return array_map(static fn (QuoteIssue $issue): array => [$issue->type, $issue->sku, $issue->title], $quote->issues);
    }

    /** @return array{string, int, ?int, bool}|null */
    private static function shipping(Quote $quote): ?array
    {
        $shipping = $quote->shipping;

        return null === $shipping ? null : [$shipping->zone, $shipping->price, $shipping->freeFrom, $shipping->free];
    }
}
