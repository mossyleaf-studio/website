<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Link;

final readonly class LinkView
{
    private function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $url,
        public string $tape,
    ) {
    }

    public static function of(Link $link): self
    {
        return new self((string) $link->id(), $link->title(), $link->description(), $link->url(), $link->tape()->value);
    }

    /**
     * @param list<Link> $links
     *
     * @return list<self>
     */
    public static function list(array $links): array
    {
        return array_map(self::of(...), $links);
    }
}
