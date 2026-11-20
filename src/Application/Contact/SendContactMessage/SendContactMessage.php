<?php

declare(strict_types=1);

namespace App\Application\Contact\SendContactMessage;

use App\Domain\Shared\Locale;

final readonly class SendContactMessage
{
    public const int MAX_NAME = 100;
    public const int MAX_EMAIL = 254;
    public const int MAX_MESSAGE = 4000;

    public function __construct(
        public string $name,
        public string $email,
        public string $message,
        public Locale $locale,
        public string $website = '',
    ) {
    }
}
