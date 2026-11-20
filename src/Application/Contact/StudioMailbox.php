<?php

declare(strict_types=1);

namespace App\Application\Contact;

use App\Application\Contact\SendContactMessage\SendContactMessage;

interface StudioMailbox
{
    public function isOpen(): bool;

    public function deliver(SendContactMessage $message): void;
}
