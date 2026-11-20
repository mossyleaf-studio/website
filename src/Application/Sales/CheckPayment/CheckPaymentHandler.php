<?php

declare(strict_types=1);

namespace App\Application\Sales\CheckPayment;

use App\Application\Sales\ConfirmPayment\ConfirmPayment;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Application\Sales\OrderView;
use App\Domain\Sales\OrderRepository;

final readonly class CheckPaymentHandler
{
    public function __construct(
        private OrderRepository $orders,
        private ConfirmPaymentHandler $confirmPayment,
    ) {
    }

    public function __invoke(CheckPayment $command): OrderView
    {
        $paymentId = $this->orders->get($command->id)->paymentId();
        if (null !== $paymentId) {
            ($this->confirmPayment)(new ConfirmPayment($paymentId));
        }

        return OrderView::of($this->orders->get($command->id));
    }
}
