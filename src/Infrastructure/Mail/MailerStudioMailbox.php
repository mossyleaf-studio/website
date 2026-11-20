<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Contact\SendContactMessage\SendContactMessage;
use App\Application\Contact\StudioMailbox;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class MailerStudioMailbox implements StudioMailbox
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        #[Autowire('%env(default::STUDIO_EMAIL)%')]
        private ?string $studio,
    ) {
    }

    public function isOpen(): bool
    {
        return null !== $this->studio && '' !== trim($this->studio);
    }

    public function deliver(SendContactMessage $message): void
    {
        $studio = trim((string) $this->studio);
        $locale = $message->locale->value;

        $this->mailer->send(new TemplatedEmail()
            ->from($studio)
            ->to($studio)
            ->replyTo(new Address($message->email, $message->name))
            ->subject($this->translator->trans('contact.subject', ['name' => $message->name], 'emails', $locale))
            ->locale($locale)
            ->textTemplate('emails/contact.txt.twig')
            ->context(['visitor' => ['name' => $message->name, 'email' => $message->email], 'text' => $message->message]));
    }
}
