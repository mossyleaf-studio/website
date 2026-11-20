<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Shop;

use App\Domain\Shared\Locale;
use App\Presentation\Web\Shop\DateRangeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;

final class DateRangeExtensionTest extends TestCase
{
    #[DataProvider('cases')]
    public function testARangeReadsAsTheSharedCaseSays(string $startsOn, string $endsOn, string $english, string $french): void
    {
        self::assertSame([$english, $french], [DateRangeExtension::format($startsOn, $endsOn, Locale::En), DateRangeExtension::format($startsOn, $endsOn, Locale::Fr)]);
    }

    public function testTheFilterWritesInThePageLanguage(): void
    {
        $locales = new LocaleSwitcher('fr', []);

        self::assertSame('12–14 juin 2027', new DateRangeExtension($locales)->range('2027-06-12', '2027-06-14'));
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function cases(): iterable
    {
        $cases = json_decode((string) file_get_contents(\dirname(__DIR__, 4).'/Fixtures/date-ranges.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($cases);

        foreach ($cases as $case) {
            self::assertIsArray($case);
            self::assertIsString($case['startsOn']);
            self::assertIsString($case['endsOn']);
            self::assertIsString($case['en']);
            self::assertIsString($case['fr']);

            yield $case['en'] => [$case['startsOn'], $case['endsOn'], $case['en'], $case['fr']];
        }
    }
}
