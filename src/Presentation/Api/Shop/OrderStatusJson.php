<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use App\Application\Sales\OrderQueries;
use App\Application\Sales\OrderStatusLine;
use App\Presentation\VerifyPayment;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class OrderStatusJson
{
    public function __construct(
        private OrderQueries $orders,
        private VerifyPayment $verifyPayment,
        #[Target('order_status')]
        private RateLimiterFactoryInterface $limiter,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function of(string $token, Request $request): ?array
    {
        $paymentId = $this->orders->paymentToVerify($token);
        if (null !== $paymentId && $this->limiter->create($request->getClientIp())->consume()->isAccepted()) {
            $this->verifyPayment->verify($paymentId);
        }

        $order = $this->orders->status($token);

        return null === $order ? null : [
            'reference' => $order->reference,
            'status' => $order->status,
            'test' => $order->test,
            'paymentUrl' => $order->paymentUrl,
            'lines' => array_map(static fn (OrderStatusLine $line): array => ['title' => $line->title, 'variant' => $line->variant, 'quantity' => $line->quantity, 'unitPrice' => $line->unitPrice, 'lineTotal' => $line->lineTotal], $order->lines),
            'goods' => $order->goods,
            'shipping' => $order->shipping,
            'total' => $order->total,
            'placedAt' => $order->placedAt->format(\DATE_ATOM),
        ];
    }
}
