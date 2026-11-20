<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class Quote
{
    /**
     * @param list<QuoteLine>  $lines
     * @param list<QuoteIssue> $issues
     */
    public function __construct(
        public array $lines,
        public array $issues,
        public int $goods,
        public ?ShippingQuote $shipping,
        public bool $deliverable,
        public int $total,
        public int $units,
    ) {
    }
}
