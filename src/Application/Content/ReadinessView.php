<?php

declare(strict_types=1);

namespace App\Application\Content;

final readonly class ReadinessView
{
    public bool $ready;

    /** @param list<ReadinessItem> $items */
    public function __construct(public array $items)
    {
        $this->ready = [] === $this->blockers();
    }

    /** @return list<string> */
    public function blockers(): array
    {
        return array_values(array_map(
            static fn (ReadinessItem $item): string => $item->key,
            array_filter($this->items, static fn (ReadinessItem $item): bool => ReadinessState::Blocker === $item->state),
        ));
    }
}
