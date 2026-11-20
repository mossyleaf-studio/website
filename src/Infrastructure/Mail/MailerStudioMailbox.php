<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Contact\SendContactMessage\SendContactMessage;
use App\Application\Contact\StudioMailbox;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class MailerStudioMailbox implements StudioMailbox
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private MailAddresses $addresses,
    ) {
    }

    public function isOpen(): bool
    {
        return $this->addresses->isOpen();
    }

    public function hasTransport(): bool
    {
        return $this->addresses->hasTransport();
    }

    public function deliver(SendContactMessage $message): void
    {
        $locale = $message->locale->value;

        $this->mailer->send(new TemplatedEmail()
            ->from($this->addresses->from)
            ->to($this->addresses->studio)
            ->replyTo(new Address($message->email, $message->name))
            ->subject($this->translator->trans('contact.subject', ['name' => $message->name], 'emails', $locale))
            ->locale($locale)
            ->textTemplate('emails/contact.txt.twig')
            ->context(['visitor' => ['name' => $message->name, 'email' => $message->email], 'text' => $message->message]));
    }
}
