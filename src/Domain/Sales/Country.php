<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Sales\Exception\UnknownCountry;
use Symfony\Component\Intl\Countries;

final readonly class Country
{
    private function __construct(public string $code)
    {
    }

    public static function of(string $code): self
    {
        if (1 !== preg_match('/^[A-Z]{2}$/', $code) || !Countries::exists($code)) {
            throw new UnknownCountry();
        }

        return new self($code);
    }
}
