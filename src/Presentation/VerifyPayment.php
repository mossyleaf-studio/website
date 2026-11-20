<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Application\Sales\ConfirmPayment\ConfirmPayment;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use Psr\Log\LoggerInterface;

final readonly class VerifyPayment
{
    public function __construct(
        private ConfirmPaymentHandler $confirmPayment,
        private LoggerInterface $logger,
    ) {
    }

    public function verify(string $paymentId): void
    {
        try {
            ($this->confirmPayment)(new ConfirmPayment($paymentId));
        } catch (\Throwable $failure) {
            $this->logger->warning('A payment could not be verified.', ['failure' => $failure::class]);
        }
    }
}
