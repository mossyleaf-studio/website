<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Sales\OrderMails;
use App\Domain\Sales\Order;

final class RecordingOrderMails implements OrderMails
{
    /** @var list<array{string, string, string, mixed}> */
    public array $sent = [];

    public ?\Throwable $failure = null;

    public function __construct(private readonly ?\Closure $probe = null)
    {
    }

    public function confirm(Order $order): void
    {
        $this->record('confirm', $order);
    }

    public function notify(Order $order): void
    {
        $this->record('notify', $order);
    }

    public function shipped(Order $order): void
    {
        $this->record('shipped', $order);
    }

    private function record(string $kind, Order $order): void
    {
        $this->sent[] = [$kind, $order->reference(), $order->status()->value, null === $this->probe ? null : ($this->probe)()];
        if (null !== $this->failure) {
            throw $this->failure;
        }
    }
}
