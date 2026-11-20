<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Artwork;
use App\Domain\Content\FontRole;
use App\Domain\Content\Link;
use App\Domain\Content\SiteText;

final readonly class ShopContentView
{
    /**
     * @param list<ShopLinkView>    $links
     * @param list<ShopArtworkView> $artworks
     */
    public function __construct(
        public string $studioName = '',
        public LocalizedText $intro = new LocalizedText(),
        public LocalizedText $metaDescription = new LocalizedText(),
        public LocalizedText $pageTitle = new LocalizedText(),
        public LocalizedText $aboutTitle = new LocalizedText(),
        public LocalizedText $aboutText = new LocalizedText(),
        public LocalizedText $galleryTitle = new LocalizedText(),
        public LocalizedText $announcement = new LocalizedText(),
        public LocalizedText $priceNote = new LocalizedText(),
        public array $links = [],
        public array $artworks = [],
        public ?ImageView $logo = null,
        public ?TypefaceView $headingFont = null,
        public ?TypefaceView $bodyFont = null,
    ) {
    }

    /**
     * @param list<Link>    $links
     * @param list<Artwork> $artworks
     */
    public static function of(SiteText $text, array $links, array $artworks): self
    {
        return new self(
            $text->studioName(),
            LocalizedText::of($text->intro(), $text->introFr()),
            LocalizedText::of($text->metaDescription(), $text->metaDescriptionFr()),
            LocalizedText::of($text->pageTitle(), $text->searchTitleFr()),
            LocalizedText::of($text->aboutTitle(), $text->aboutTitleFr()),
            LocalizedText::of($text->aboutText(), $text->aboutTextFr()),
            LocalizedText::of($text->galleryTitle(), $text->galleryTitleFr()),
            LocalizedText::of($text->announcement(), $text->announcementFr()),
            LocalizedText::of($text->priceNote(), $text->priceNoteFr()),
            array_map(ShopLinkView::of(...), $links),
            array_map(ShopArtworkView::of(...), $artworks),
            ImageView::ofLogo($text->logo()),
            TypefaceView::of($text->font(FontRole::Heading)),
            TypefaceView::of($text->font(FontRole::Body)),
        );
    }
}
