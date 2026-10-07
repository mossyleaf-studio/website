<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Artwork;
use App\Domain\Content\Link;
use App\Domain\Content\SiteFont;
use App\Domain\Content\SiteText;

final readonly class SiteView
{
    /**
     * @param array{title: string, text: string}                $homeNote
     * @param array{title: string, paragraphs: list<string>}    $about
     * @param array{title: string, artworks: list<ArtworkView>} $gallery
     * @param list<LinkView>                                    $links
     * @param array{heading: SiteFont, body: SiteFont}          $fonts
     */
    private function __construct(
        public string $studioName,
        public string $intro,
        public string $metaDescription,
        public string $pageTitle,
        public ?ImageView $logo,
        public array $homeNote,
        public array $about,
        public ?ArtworkView $featured,
        public array $gallery,
        public array $links,
        public array $fonts,
    ) {
    }

    /**
     * @param list<Link>    $links
     * @param list<Artwork> $artworks
     */
    public static function of(SiteText $text, array $links, array $artworks): self
    {
        $featured = array_find($artworks, static fn (Artwork $artwork): bool => $artwork->isFeatured());
        $gallery = array_values(array_filter($artworks, static fn (Artwork $artwork): bool => !$artwork->isFeatured()));

        return new self(
            $text->studioName(),
            $text->intro(),
            $text->metaDescription(),
            $text->pageTitle(),
            ImageView::ofLogo($text->logo()),
            ['title' => $text->homeNoteTitle(), 'text' => $text->homeNoteText()],
            ['title' => $text->aboutTitle(), 'paragraphs' => $text->aboutParagraphs()],
            null === $featured ? null : ArtworkView::of($featured),
            ['title' => $text->galleryTitle(), 'artworks' => ArtworkView::list($gallery)],
            LinkView::list($links),
            ['heading' => $text->headingFont(), 'body' => $text->bodyFont()],
        );
    }
}
