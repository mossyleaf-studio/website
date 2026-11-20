<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Contact;

use App\Application\Contact\SendContactMessage\SendContactMessage;
use App\Application\Contact\SendContactMessage\SendContactMessageHandler;
use App\Application\Contact\StudioMailbox;
use App\Domain\Shared\Locale;
use PHPUnit\Framework\TestCase;

final class SendContactMessageHandlerTest extends TestCase
{
    public function testAMessageIsDeliveredOnce(): void
    {
        $mailbox = self::mailbox();
        $message = new SendContactMessage('Lou', 'lou@example.test', 'Hello', Locale::Fr);

        new SendContactMessageHandler($mailbox)($message);

        self::assertSame([$message], $mailbox->delivered);
    }

    public function testAMessageCaughtInTheHoneypotIsDropped(): void
    {
        $mailbox = self::mailbox();

        new SendContactMessageHandler($mailbox)(new SendContactMessage('Bot', 'bot@example.test', 'Buy', Locale::En, 'https://spam.test'));

        self::assertSame([], $mailbox->delivered);
    }

    /** @return StudioMailbox&object{delivered: list<SendContactMessage>} */
    private static function mailbox(): StudioMailbox
    {
        return new class implements StudioMailbox {
            /** @var list<SendContactMessage> */
            public array $delivered = [];

            public function isOpen(): bool
            {
                return true;
            }

            public function hasTransport(): bool
            {
                return true;
            }

            public function deliver(SendContactMessage $message): void
            {
                $this->delivered[] = $message;
            }
        };
    }
}
