<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use App\Application\Sales\Quote;
use App\Application\Sales\QuoteIssue;
use App\Application\Sales\QuoteLine;
use App\Domain\Shared\Locale;
use App\Presentation\Web\Shop\CatalogueProps;
use App\Presentation\Web\Shop\ShopUrls;

final readonly class QuoteJson
{
    public function __construct(private ShopUrls $urls)
    {
    }

    /** @return array<string, mixed> */
    public function of(Quote $quote, Locale $locale, bool $preview): array
    {
        return [
            'lines' => array_map(fn (QuoteLine $line): array => [
                'sku' => $line->sku,
                'title' => $line->title,
                'variant' => $line->variant,
                'unitPrice' => $line->unitPrice,
                'quantity' => $line->quantity,
                'lineTotal' => $line->lineTotal,
                'image' => null === $line->photo ? null : CatalogueProps::image($line->photo, ''),
                'url' => $this->urls->to('shop_product', $locale, $preview, ['slug' => $line->slug]),
                'available' => $line->available,
            ], $quote->lines),
            'issues' => array_map(static fn (QuoteIssue $issue): array => ['type' => $issue->type, 'sku' => $issue->sku, 'title' => $issue->title], $quote->issues),
            'goods' => $quote->goods,
            'shipping' => null === $quote->shipping ? null : ['zone' => $quote->shipping->zone, 'price' => $quote->shipping->price, 'freeFrom' => $quote->shipping->freeFrom, 'free' => $quote->shipping->free],
            'deliverable' => $quote->deliverable,
            'total' => $quote->total,
            'units' => $quote->units,
        ];
    }
}
