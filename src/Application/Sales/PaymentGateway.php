<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface PaymentGateway
{
    public function isReady(): bool;

    public function open(string $reference, int $amount, string $description, string $webhookUrl, string $redirectUrl, \DateTimeImmutable $validUntil): OpenedCheckout;

    public function find(string $id): CheckoutState;
}
