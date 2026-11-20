<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class QuoteIssue
{
    public const string REMOVED = 'removed';
    public const string REDUCED = 'reduced';

    private function __construct(
        public string $type,
        public string $sku,
        public ?string $title,
    ) {
    }

    public static function removed(string $sku, ?string $title): self
    {
        return new self(self::REMOVED, $sku, $title);
    }

    public static function reduced(string $sku, string $title): self
    {
        return new self(self::REDUCED, $sku, $title);
    }
}
