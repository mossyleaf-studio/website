<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use App\Application\Sales\CartLine;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class QuotePayload
{
    /** @param list<QuoteLinePayload> $lines */
    public function __construct(
        #[Assert\Valid]
        public array $lines,
        public ?string $country = null,
    ) {
    }

    /** @return list<CartLine> */
    public function lines(): array
    {
        return array_map(static fn (QuoteLinePayload $line): CartLine => $line->line(), $this->lines);
    }
}
