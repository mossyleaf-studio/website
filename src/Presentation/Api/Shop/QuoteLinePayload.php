<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use App\Application\Sales\CartLine;
use App\Domain\Catalogue\ProductVariant;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class QuoteLinePayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'text.required')]
        #[Assert\Length(max: ProductVariant::MAX_SKU, maxMessage: 'text.too_long')]
        public string $sku,
        public int $quantity,
    ) {
    }

    public function line(): CartLine
    {
        return new CartLine($this->sku, $this->quantity);
    }
}
