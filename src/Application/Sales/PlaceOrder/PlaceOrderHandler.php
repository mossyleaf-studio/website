<?php

declare(strict_types=1);

namespace App\Application\Sales\PlaceOrder;

use App\Application\AtomicChange;
use App\Application\Sales\CartPricer;
use App\Application\Sales\OrderUrls;
use App\Application\Sales\PaymentGateway;
use App\Application\Sales\QuoteLine;
use App\Domain\Catalogue\ProductRepository;
use App\Domain\Sales\Country;
use App\Domain\Sales\Exception\CartOutdated;
use App\Domain\Sales\Exception\EmptyCart;
use App\Domain\Sales\Exception\PaymentUnavailable;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderReference;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\Zone;
use Psr\Clock\ClockInterface;

final readonly class PlaceOrderHandler
{
    public function __construct(
        private CartPricer $pricer,
        private ProductRepository $products,
        private OrderRepository $orders,
        private AtomicChange $atomicChange,
        private PaymentGateway $gateway,
        private OrderUrls $urls,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PlaceOrder $command): PlacedOrder
    {
        if ([] === $command->lines) {
            throw new EmptyCart();
        }

        $order = $this->atomicChange->apply(fn (): Order => $this->hold($command));

        try {
            $checkout = $this->gateway->open(
                $order->reference(),
                $order->total(),
                ($order->isTest() ? 'TEST ' : '').'mossyleaf.studio order '.$order->reference(),
                $this->urls->webhook(),
                $this->urls->statusPage($order->token(), $order->locale(), $order->isTest()),
                $order->createdAt()->modify(Order::PAYMENT_WINDOW),
            );
        } catch (\Throwable) {
            $this->atomicChange->apply(static fn () => $order->fail());

            throw new PaymentUnavailable();
        }

        $this->atomicChange->apply(static fn () => $order->openPayment($checkout->id, $checkout->url));

        return new PlacedOrder($order->reference(), $order->token(), $checkout->url);
    }

    private function hold(PlaceOrder $command): Order
    {
        $this->products->lock();

        $quote = $this->pricer->quote($command->lines, $command->country, $command->preview, $command->locale);
        if ([] !== $quote->issues || !$quote->deliverable || null === $quote->shipping || $quote->total !== $command->expectedTotal) {
            throw new CartOutdated();
        }

        $order = Order::place(
            OrderReference::unused($this->orders->hasReference(...)),
            $command->preview,
            $command->locale,
            $command->delivery,
            Country::of($command->country),
            Zone::from($quote->shipping->zone),
            array_map(static fn (QuoteLine $line): array => [
                'sku' => $line->sku,
                'name' => $line->name,
                'title' => $line->title,
                'variant' => $line->variant,
                'unitPrice' => $line->unitPrice,
                'quantity' => $line->quantity,
            ], $quote->lines),
            $quote->shipping->price,
            $this->clock->now(),
        );
        $this->orders->add($order);

        return $order;
    }
}
