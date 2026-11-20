<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Shop;

use App\Presentation\Web\Shop\SiteLabelExtension;
use App\Presentation\Web\Shop\SiteLabels;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;

final class SiteLabelExtensionTest extends TestCase
{
    public function testItFillsTheNamedPlaceholders(): void
    {
        self::assertSame('© 2026 mossyleaf.studio', self::filter('en')('footer.copyright', ['year' => 2026, 'studio' => 'mossyleaf.studio']));
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function counts(): iterable
    {
        yield 'English zero is plural' => ['en', 0, '0 items'];
        yield 'English one is singular' => ['en', 1, '1 item'];
        yield 'English two is plural' => ['en', 2, '2 items'];
        yield 'French zero is singular' => ['fr', 0, '0 article'];
        yield 'French one is singular' => ['fr', 1, '1 article'];
        yield 'French two is plural' => ['fr', 2, '2 articles'];
    }

    #[DataProvider('counts')]
    public function testItPicksThePluralFormOfTheLanguage(string $locale, int $count, string $expected): void
    {
        self::assertSame($expected, self::filter($locale)('cart.count', ['count' => $count]));
    }

    public function testAnUnknownKeyShowsItself(): void
    {
        self::assertSame('nowhere.to.be.found', self::filter('fr')('nowhere.to.be.found'));
        self::assertSame('cart', self::filter('fr')('cart'));
    }

    public function testAnUnknownLocaleReadsEnglish(): void
    {
        self::assertSame('2 items', self::filter('de')('cart.count', ['count' => 2]));
    }

    /** @return \Closure(string, array<string, scalar>=): string */
    private static function filter(string $locale): \Closure
    {
        $extension = new SiteLabelExtension(new SiteLabels(\dirname(__DIR__, 4).'/Fixtures/i18n'), new LocaleSwitcher($locale, []));

        return $extension->label(...);
    }
}
