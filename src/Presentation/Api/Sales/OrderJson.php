<?php

declare(strict_types=1);

namespace App\Presentation\Api\Sales;

use App\Application\Sales\OrderLineView;
use App\Application\Sales\OrderView;
use App\Domain\Shared\Locale;
use App\Presentation\Web\Shop\ShopUrls;

final readonly class OrderJson
{
    public function __construct(private ShopUrls $urls)
    {
    }

    /** @return array<string, mixed> */
    public function of(OrderView $order): array
    {
        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status,
            'test' => $order->test,
            'locale' => $order->locale,
            'placedAt' => $order->placedAt->format(\DATE_ATOM),
            'expiresAt' => $order->expiresAt->format(\DATE_ATOM),
            'paidAt' => $order->paidAt?->format(\DATE_ATOM),
            'shippedAt' => $order->shippedAt?->format(\DATE_ATOM),
            'cancelledAt' => $order->cancelledAt?->format(\DATE_ATOM),
            'email' => $order->email,
            'name' => $order->name,
            'phone' => $order->phone,
            'line1' => $order->line1,
            'line2' => $order->line2,
            'postalCode' => $order->postalCode,
            'city' => $order->city,
            'region' => $order->region,
            'country' => $order->country,
            'zone' => $order->zone,
            'lines' => array_map(static fn (OrderLineView $line): array => ['sku' => $line->sku, 'name' => $line->name, 'title' => $line->title, 'variant' => $line->variant, 'unitPrice' => $line->unitPrice, 'quantity' => $line->quantity, 'lineTotal' => $line->lineTotal], $order->lines),
            'goods' => $order->goods,
            'shipping' => $order->shipping,
            'total' => $order->total,
            'units' => $order->units,
            'transactionCode' => $order->transactionCode,
            'trackingNumber' => $order->trackingNumber,
            'hasCheckout' => $order->hasCheckout,
            'statusUrl' => $this->urls->to('shop_order', Locale::from($order->locale), $order->test, ['token' => $order->token]),
        ];
    }
}
