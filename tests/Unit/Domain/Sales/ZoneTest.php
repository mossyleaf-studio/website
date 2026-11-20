<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\Country;
use App\Domain\Sales\Exception\UnknownCountry;
use App\Domain\Sales\Zone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Countries;

final class ZoneTest extends TestCase
{
    /** @return iterable<string, array{string, Zone}> */
    public static function countries(): iterable
    {
        yield 'France' => ['FR', Zone::France];
        yield 'Monaco' => ['MC', Zone::France];
        yield 'Germany' => ['DE', Zone::Eu];
        yield 'Belgium' => ['BE', Zone::Eu];
        yield 'Italy' => ['IT', Zone::Eu];
        yield 'Ireland' => ['IE', Zone::Eu];
        yield 'Cyprus' => ['CY', Zone::Eu];
        yield 'United Kingdom' => ['GB', Zone::World];
        yield 'Switzerland' => ['CH', Zone::World];
        yield 'United States' => ['US', Zone::World];
        yield 'Japan' => ['JP', Zone::World];
    }

    #[DataProvider('countries')]
    public function testEachCountryBelongsToOneZone(string $code, Zone $zone): void
    {
        self::assertSame($zone, Zone::of(Country::of($code)));
    }

    public function testTheEuropeanZoneHoldsExactlyTheOtherMemberStates(): void
    {
        $members = array_filter(self::codes(), static fn (string $code): bool => Zone::Eu === Zone::of(Country::of($code)));

        self::assertSame(['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'], array_values($members));
    }

    /** @return list<string> */
    private static function codes(): array
    {
        $codes = array_keys(Countries::getNames('en'));
        sort($codes);

        return $codes;
    }

    /** @return iterable<string, array{string}> */
    public static function unknownCodes(): iterable
    {
        yield 'no such country' => ['ZZ'];
        yield 'lowercase' => ['fr'];
        yield 'three letters' => ['FRA'];
        yield 'empty' => [''];
    }

    #[DataProvider('unknownCodes')]
    public function testAnUnknownCountryCodeIsRefused(string $code): void
    {
        $this->expectException(UnknownCountry::class);

        Country::of($code);
    }
}
