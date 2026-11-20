<?php

declare(strict_types=1);

namespace App\Domain\Sales;

final class OrderReference
{
    public const int LENGTH = 12;
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const int ATTEMPTS = 10;

    /** @param callable(string): bool $taken */
    public static function unused(callable $taken): string
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; ++$attempt) {
            $reference = 'ML-'.self::group().'-'.self::group();
            if (!$taken($reference)) {
                return $reference;
            }
        }

        throw new \RuntimeException('No free order reference after '.self::ATTEMPTS.' attempts.');
    }

    private static function group(): string
    {
        $group = '';
        for ($i = 0; $i < 4; ++$i) {
            $group .= self::ALPHABET[random_int(0, 31)];
        }

        return $group;
    }
}
