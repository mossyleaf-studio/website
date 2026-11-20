<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exception;

use App\Domain\Shared\Exception\DomainException;

final class NotATestOrder extends DomainException
{
    public function __construct(string $reference)
    {
        parent::__construct('sales.not_a_test_order', ['reference' => $reference]);
    }
}
