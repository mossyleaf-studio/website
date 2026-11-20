<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class MailAddresses
{
    public string $from;
    public string $studio;

    public function __construct(
        #[Autowire('%env(default::MAILER_FROM)%')]
        ?string $from,
        #[Autowire('%env(default::STUDIO_EMAIL)%')]
        ?string $studio,
    ) {
        $this->from = trim((string) $from);
        $this->studio = trim((string) $studio);
    }

    public function isOpen(): bool
    {
        return '' !== $this->from && '' !== $this->studio;
    }
}
