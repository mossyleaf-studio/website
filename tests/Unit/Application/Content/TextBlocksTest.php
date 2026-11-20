<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Content;

use App\Application\Content\TextBlocks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextBlocksTest extends TestCase
{
    /** @param list<mixed> $blocks */
    #[DataProvider('cases')]
    public function testTheTextReadsAsTheSharedCaseSays(string $text, array $blocks): void
    {
        self::assertSame($blocks, TextBlocks::parse($text));
    }

    /** @return iterable<string, array{string, list<mixed>}> */
    public static function cases(): iterable
    {
        $cases = json_decode((string) file_get_contents(\dirname(__DIR__, 3).'/Fixtures/rich-blocks.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($cases);

        foreach ($cases as $case) {
            self::assertIsArray($case);
            self::assertIsString($case['name']);
            self::assertIsString($case['text']);
            self::assertIsArray($case['blocks']);

            yield $case['name'] => [$case['text'], array_values($case['blocks'])];
        }
    }
}
