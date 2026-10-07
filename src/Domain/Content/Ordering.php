<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Content\Exception\OrderMismatch;
use Symfony\Component\Uid\Ulid;

final class Ordering
{
    /**
     * @template T of Link|Artwork
     *
     * @param list<T>      $items
     * @param list<string> $orderedIds
     */
    public static function apply(array $items, array $orderedIds): void
    {
        $byId = [];
        foreach ($items as $item) {
            $byId[(string) $item->id()] = $item;
        }

        $ids = array_map(self::normalized(...), $orderedIds);
        if (\count($ids) !== \count($byId) || \count(array_unique($ids)) !== \count($ids) || [] !== array_diff($ids, array_keys($byId))) {
            throw new OrderMismatch();
        }

        foreach ($ids as $position => $id) {
            $byId[$id]->moveTo($position);
        }
    }

    private static function normalized(string $id): string
    {
        try {
            return (string) Ulid::fromString($id);
        } catch (\InvalidArgumentException) {
            return $id;
        }
    }
}
