<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Shared\Locale;

interface OrderUrls
{
    public function statusPage(string $token, Locale $locale, bool $test): string;

    public function webhook(): string;
}
