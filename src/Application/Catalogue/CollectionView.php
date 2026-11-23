<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use App\Domain\Catalogue\Collection;

final readonly class CollectionView
{
    private function __construct(
        public string $id,
        public string $name,
        public string $label,
        public string $labelFr,
        public bool $shown,
        public int $position,
        public int $onSale,
    ) {
    }

    /** @param array<string, int> $onSale */
    public static function of(Collection $collection, array $onSale): self
    {
        $id = (string) $collection->id();

        return new self($id, $collection->name(), $collection->label()->en, $collection->label()->fr, $collection->isShown(), $collection->position(), $onSale[$id] ?? 0);
    }

    /**
     * @param list<Collection>   $collections
     * @param array<string, int> $onSale
     *
     * @return list<self>
     */
    public static function list(array $collections, array $onSale): array
    {
        return array_map(static fn (Collection $collection): self => self::of($collection, $onSale), $collections);
    }
}
