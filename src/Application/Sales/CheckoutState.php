<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Order;

final readonly class CheckoutState
{
    public const string CURRENCY = 'EUR';

    public function __construct(
        public PaymentState $state,
        public string $reference,
        public int $amount,
        public string $currency,
        public ?string $transactionCode,
    ) {
    }

    public function pays(Order $order): bool
    {
        return PaymentState::Paid === $this->state
            && $this->reference === $order->reference()
            && $this->amount === $order->total()
            && self::CURRENCY === $this->currency;
    }
}
