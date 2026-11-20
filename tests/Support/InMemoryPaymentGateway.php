<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Sales\CheckoutState;
use App\Application\Sales\OpenedCheckout;
use App\Application\Sales\PaymentGateway;
use App\Application\Sales\PaymentState;
use App\Domain\Sales\Exception\PaymentUnavailable;

final class InMemoryPaymentGateway implements PaymentGateway
{
    public bool $ready = true;
    public bool $down = false;

    /** @var array<string, CheckoutState> */
    public array $checkouts = [];

    /** @var list<array{reference: string, amount: int, description: string, webhookUrl: string, redirectUrl: string, validUntil: \DateTimeImmutable}> */
    public array $opened = [];

    /** @var list<string> */
    public array $asked = [];

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function open(string $reference, int $amount, string $description, string $webhookUrl, string $redirectUrl, \DateTimeImmutable $validUntil): OpenedCheckout
    {
        if ($this->down) {
            throw new PaymentUnavailable();
        }
        $this->opened[] = ['reference' => $reference, 'amount' => $amount, 'description' => $description, 'webhookUrl' => $webhookUrl, 'redirectUrl' => $redirectUrl, 'validUntil' => $validUntil];
        $id = 'checkout-'.\count($this->opened);
        $this->checkouts[$id] = new CheckoutState(PaymentState::Pending, $reference, $amount, 'EUR', null);

        return new OpenedCheckout($id, 'https://pay.test/'.$id);
    }

    public function find(string $id): CheckoutState
    {
        $this->asked[] = $id;
        if ($this->down) {
            throw new PaymentUnavailable();
        }

        return $this->checkouts[$id] ?? throw new PaymentUnavailable();
    }

    public function script(string $id, PaymentState $state, ?string $transactionCode = null, ?string $reference = null, ?int $amount = null, ?string $currency = null): void
    {
        $known = $this->checkouts[$id] ?? throw new \LogicException('No checkout '.$id);
        $this->checkouts[$id] = new CheckoutState($state, $reference ?? $known->reference, $amount ?? $known->amount, $currency ?? $known->currency, $transactionCode);
    }

    public function pay(string $id, string $transactionCode = 'TX-1'): void
    {
        $this->script($id, PaymentState::Paid, $transactionCode);
    }
}
