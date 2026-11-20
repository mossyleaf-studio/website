<?php

declare(strict_types=1);

namespace App\Application\Content;

final readonly class ReadinessItem
{
    public function __construct(
        public string $key,
        public ReadinessState $state,
        public ?int $count = null,
    ) {
    }

    public static function unless(string $key, bool $missing, ReadinessState $otherwise, ?int $count = null): self
    {
        return new self($key, $missing ? $otherwise : ReadinessState::Ok, $count);
    }
}
