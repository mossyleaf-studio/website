<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\SiteText;

final readonly class SiteTextView
{
    private function __construct(
        public string $studioName,
        public string $intro,
        public string $metaDescription,
        public string $homeNoteTitle,
        public string $homeNoteText,
        public string $aboutTitle,
        public string $aboutText,
        public string $galleryTitle,
    ) {
    }

    public static function of(SiteText $text): self
    {
        return new self(
            $text->studioName(),
            $text->intro(),
            $text->metaDescription(),
            $text->homeNoteTitle(),
            $text->homeNoteText(),
            $text->aboutTitle(),
            $text->aboutText(),
            $text->galleryTitle(),
        );
    }
}
