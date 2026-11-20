<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Link;

final readonly class ShopLinkView
{
    public function __construct(
        public string $id,
        public LocalizedText $title,
        public LocalizedText $description,
        public string $url,
        public string $tape,
    ) {
    }

    public static function of(Link $link): self
    {
        return new self(
            (string) $link->id(),
            LocalizedText::of($link->title(), $link->titleFr()),
            LocalizedText::of($link->description(), $link->descriptionFr()),
            $link->url(),
            $link->tape()->value,
        );
    }
}
