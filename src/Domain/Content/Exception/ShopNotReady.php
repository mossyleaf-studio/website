<?php

declare(strict_types=1);

namespace App\Domain\Content\Exception;

use App\Domain\Shared\Exception\DomainException;

final class ShopNotReady extends DomainException
{
    /** @param list<string> $blockers */
    public function __construct(public readonly array $blockers)
    {
        parent::__construct('content.shop_not_ready', ['count' => \count($blockers)]);
    }
}
