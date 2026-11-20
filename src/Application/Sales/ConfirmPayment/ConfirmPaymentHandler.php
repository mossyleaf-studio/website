<?php

declare(strict_types=1);

namespace App\Application\Sales\ConfirmPayment;

use App\Application\AtomicChange;
use App\Application\Sales\CheckoutState;
use App\Application\Sales\OrderMails;
use App\Application\Sales\PaymentGateway;
use App\Application\Sales\PaymentState;
use App\Domain\Catalogue\ProductRepository;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class ConfirmPaymentHandler
{
    public function __construct(
        private PaymentGateway $gateway,
        private OrderRepository $orders,
        private ProductRepository $products,
        private AtomicChange $atomicChange,
        private OrderMails $mails,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ConfirmPayment $command): void
    {
        if (true !== $this->orders->withPaymentId($command->paymentId)?->status()->allows(OrderStatus::Paid)) {
            return;
        }

        $checkout = $this->gateway->find($command->paymentId);
        $paid = $this->atomicChange->apply(fn (): ?Order => $this->settle($command->paymentId, $checkout));
        if (null === $paid) {
            return;
        }

        $this->send('confirm', fn () => $this->mails->confirm($paid), $paid);
        $this->send('notify', fn () => $this->mails->notify($paid), $paid);
    }

    /** @param callable(): void $send */
    private function send(string $mail, callable $send, Order $order): void
    {
        try {
            $send();
        } catch (\Throwable $failure) {
            $this->logger->error('The {mail} mail of an order could not be sent.', ['mail' => $mail, 'reference' => $order->reference(), 'failure' => $failure::class]);
        }
    }

    private function settle(string $paymentId, CheckoutState $checkout): ?Order
    {
        $order = $this->orders->lockWithPaymentId($paymentId);
        if (null === $order) {
            return null;
        }

        if ($checkout->pays($order)) {
            if (!$order->status()->allows(OrderStatus::Paid)) {
                return null;
            }
            $this->products->lock();
            $this->take($order);
            $order->pay($checkout->transactionCode, $this->clock->now());

            return $order;
        }

        if (PaymentState::Paid === $checkout->state) {
            $this->logger->warning('A paid checkout does not match its order.', ['reference' => $order->reference()]);
        }
        if (OrderStatus::Pending !== $order->status()) {
            return null;
        }

        match (true) {
            PaymentState::Failed === $checkout->state => $order->fail(),
            PaymentState::Expired === $checkout->state, !$order->isHeldAt($this->clock->now()) => $order->expire(),
            default => null,
        };

        return null;
    }

    private function take(Order $order): void
    {
        $quantities = [];
        foreach ($order->lines() as $line) {
            $quantities[$line->sku()] = ($quantities[$line->sku()] ?? 0) + $line->quantity();
        }
        foreach ($this->products->variantsWithSkus(array_map(strval(...), array_keys($quantities))) as $variant) {
            $variant->take($quantities[$variant->sku()]);
        }
    }
}
