<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\OrderReference;
use PHPUnit\Framework\TestCase;

final class OrderReferenceTest extends TestCase
{
    public function testAReferenceIsTwoGroupsOfFourCrockfordCharacters(): void
    {
        $references = [];
        for ($i = 0; $i < 200; ++$i) {
            $references[] = OrderReference::unused(static fn (): bool => false);
        }

        foreach ($references as $reference) {
            self::assertMatchesRegularExpression('/^ML-[0-9ABCDEFGHJKMNPQRSTVWXYZ]{4}-[0-9ABCDEFGHJKMNPQRSTVWXYZ]{4}$/', $reference);
        }
        self::assertGreaterThan(195, \count(array_unique($references)));
    }

    public function testATakenReferenceIsDrawnAgain(): void
    {
        $asked = [];

        $reference = OrderReference::unused(static function (string $reference) use (&$asked): bool {
            $asked[] = $reference;

            return \count($asked) < 3;
        });

        self::assertCount(3, $asked);
        self::assertSame($asked[2], $reference);
        self::assertCount(3, array_unique($asked));
    }

    public function testDrawingStopsAfterTenCollisions(): void
    {
        $asked = 0;

        try {
            OrderReference::unused(static function () use (&$asked): bool {
                ++$asked;

                return true;
            });
            self::fail('No reference should be found.');
        } catch (\RuntimeException) {
            self::assertSame(10, $asked);
        }
    }
}
