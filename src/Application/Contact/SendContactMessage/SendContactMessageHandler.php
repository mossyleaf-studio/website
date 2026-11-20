<?php

declare(strict_types=1);

namespace App\Application\Contact\SendContactMessage;

use App\Application\Contact\StudioMailbox;

final readonly class SendContactMessageHandler
{
    public function __construct(private StudioMailbox $mailbox)
    {
    }

    public function __invoke(SendContactMessage $command): void
    {
        if ('' !== $command->website) {
            return;
        }

        $this->mailbox->deliver($command);
    }
}
