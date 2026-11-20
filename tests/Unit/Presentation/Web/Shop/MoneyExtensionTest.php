<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Shop;

use App\Presentation\Web\Shop\MoneyExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;

final class MoneyExtensionTest extends TestCase
{
    /** @return iterable<string, array{string, int, string}> */
    public static function prices(): iterable
    {
        yield 'french' => ['fr', 1500, "15,00\u{a0}€"];
        yield 'french thousands' => ['fr', 123456, "1\u{202f}234,56\u{a0}€"];
        yield 'english' => ['en', 1500, '€15.00'];
        yield 'english thousands' => ['en', 123456, '€1,234.56'];
        yield 'cents' => ['en', 5, '€0.05'];
    }

    #[DataProvider('prices')]
    public function testCentsAreWrittenInEurosInThePageLanguage(string $locale, int $cents, string $expected): void
    {
        self::assertSame($expected, new MoneyExtension(new LocaleSwitcher($locale, []))->money($cents));
    }
}
