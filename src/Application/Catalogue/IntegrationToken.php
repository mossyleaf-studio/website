<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class IntegrationToken
{
    private const int MIN_LENGTH = 32;

    public function __construct(
        #[\SensitiveParameter]
        #[Autowire(env: 'default::MOSSYTRUNK_TOKEN')]
        private ?string $token,
    ) {
    }

    public function isUsable(): bool
    {
        return self::accepts((string) $this->token);
    }

    public static function accepts(#[\SensitiveParameter] string $token): bool
    {
        return \strlen($token) >= self::MIN_LENGTH;
    }
}
