<?php

declare(strict_types=1);

namespace App\Application\Sales\SettleStaleOrders;

use App\Application\AtomicChange;
use App\Application\Sales\ConfirmPayment\ConfirmPayment;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Domain\Sales\Exception\PaymentUnavailable;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class SettleStaleOrdersHandler
{
    public const int MAX_CHECKS = 20;
    public const string KEEP_UNPAID = '-14 days';
    public const string MAX_RUN = '+5 seconds';

    public function __construct(
        private OrderRepository $orders,
        private ConfirmPaymentHandler $confirmPayment,
        private AtomicChange $atomicChange,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(): void
    {
        $checks = self::MAX_CHECKS;
        $verified = [];
        $deadline = $this->clock->now()->modify(self::MAX_RUN);

        try {
            foreach ($this->orders->staleHolds($this->clock->now()) as $order) {
                $paymentId = $order->paymentId();
                if (null === $paymentId) {
                    $this->atomicChange->apply(fn () => $this->expire($order->id()));
                } elseif ($checks > 0 && $this->clock->now() < $deadline) {
                    --$checks;
                    ($this->confirmPayment)(new ConfirmPayment($paymentId));
                    $verified[] = $paymentId;
                }
            }

            foreach ($this->orders->unpaidPlacedBefore($this->clock->now()->modify(self::KEEP_UNPAID)) as $order) {
                $paymentId = $order->paymentId();
                if (null !== $paymentId && !\in_array($paymentId, $verified, true)) {
                    if (0 === $checks || $this->clock->now() >= $deadline) {
                        continue;
                    }
                    --$checks;
                    ($this->confirmPayment)(new ConfirmPayment($paymentId));
                }
                $this->atomicChange->apply(fn () => $this->purge($order->id()));
            }
        } catch (PaymentUnavailable) {
        }
    }

    private function expire(Ulid $id): void
    {
        $order = $this->orders->lock($id);
        if (OrderStatus::Pending === $order->status() && !$order->isHeldAt($this->clock->now())) {
            $order->expire();
        }
    }

    private function purge(Ulid $id): void
    {
        $order = $this->orders->lock($id);
        if (\in_array($order->status(), [OrderStatus::Failed, OrderStatus::Expired], true)) {
            $this->orders->remove($order);
        }
    }
}
