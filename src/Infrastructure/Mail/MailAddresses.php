<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class MailAddresses
{
    private const string NULL_TRANSPORT = 'null://';

    public string $from;
    public string $studio;
    private string $transport;

    public function __construct(
        #[Autowire('%env(default::MAILER_FROM)%')]
        ?string $from,
        #[Autowire('%env(default::STUDIO_EMAIL)%')]
        ?string $studio,
        #[\SensitiveParameter]
        #[Autowire('%env(default::MAILER_DSN)%')]
        ?string $transport = null,
    ) {
        $this->from = trim((string) $from);
        $this->studio = trim((string) $studio);
        $this->transport = strtolower(trim((string) $transport));
    }

    public function hasTransport(): bool
    {
        return '' !== $this->transport && !str_starts_with($this->transport, self::NULL_TRANSPORT);
    }

    public function isOpen(): bool
    {
        return '' !== $this->from && '' !== $this->studio;
    }
}
