<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Sales\OrderMails;
use App\Application\Sales\OrderUrls;
use App\Domain\Sales\Order;
use App\Domain\Shared\Locale;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class MailerOrderMails implements OrderMails
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private OrderUrls $urls,
        private MailAddresses $addresses,
    ) {
    }

    public function confirm(Order $order): void
    {
        if (!$this->addresses->isOpen()) {
            return;
        }

        $locale = $order->locale();
        $this->mailer->send($this->mail($order, $locale, 'order_confirmation')
            ->to($order->email())
            ->replyTo($this->addresses->studio)
            ->textTemplate('emails/order_confirmation.txt.twig')
            ->context([...$this->context($order, $locale), 'statusUrl' => $this->urls->statusPage($order->token(), $locale, $order->isTest())]));
    }

    public function notify(Order $order): void
    {
        if (!$this->addresses->isOpen()) {
            return;
        }

        $this->mailer->send($this->mail($order, Locale::Fr, 'order_notice')
            ->to($this->addresses->studio)
            ->replyTo(new Address($order->email(), $order->name()))
            ->textTemplate('emails/order_notice.txt.twig')
            ->context($this->context($order, Locale::Fr)));
    }

    private function mail(Order $order, Locale $locale, string $kind): TemplatedEmail
    {
        $subject = $this->translator->trans($kind.'.subject', ['reference' => $order->reference(), 'total' => $order->total() / 100], 'emails', $locale->value);
        if ($order->isTest()) {
            $subject = $this->translator->trans('order.test_subject', ['subject' => $subject], 'emails', $locale->value);
        }

        return new TemplatedEmail()
            ->from($this->addresses->from)
            ->subject($subject)
            ->locale($locale->value);
    }

    /** @return array<string, mixed> */
    private function context(Order $order, Locale $locale): array
    {
        return ['order' => $order, 'country' => Countries::getName($order->country(), $locale->value)];
    }
}
