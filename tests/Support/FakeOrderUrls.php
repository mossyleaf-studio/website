<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Sales\OrderUrls;
use App\Domain\Shared\Locale;

final class FakeOrderUrls implements OrderUrls
{
    public function statusPage(string $token, Locale $locale, bool $test): string
    {
        return 'https://shop.test/'.($test ? 'beta/' : '').$locale->value.'/order/'.$token;
    }

    public function webhook(): string
    {
        return 'https://shop.test/webhooks/sumup';
    }
}
