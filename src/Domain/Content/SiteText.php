<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Shared\Exception\EmptyText;
use App\Domain\Shared\Exception\TextTooLong;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'site_text')]
class SiteText
{
    public const int MAX_STUDIO_NAME = 60;
    public const int MAX_INTRO = 200;
    public const int MAX_META_DESCRIPTION = 300;
    public const int MAX_SEARCH_TITLE = 70;
    public const int MAX_TITLE = 80;
    public const int MAX_HOME_NOTE = 600;
    public const int MAX_ABOUT = 2000;
    public const int MAX_ANNOUNCEMENT = 160;
    public const int MAX_PRICE_NOTE = 160;

    private const int ID = 1;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    #[ORM\Column(length: self::MAX_STUDIO_NAME)]
    private string $studioName;

    #[ORM\Column(length: self::MAX_INTRO)]
    private string $intro;

    #[ORM\Column(length: self::MAX_META_DESCRIPTION)]
    private string $metaDescription;

    #[ORM\Column(length: self::MAX_SEARCH_TITLE, options: ['default' => ''])]
    private string $searchTitle = '';

    #[ORM\Column(length: self::MAX_TITLE)]
    private string $homeNoteTitle;

    #[ORM\Column(length: self::MAX_HOME_NOTE)]
    private string $homeNoteText;

    #[ORM\Column(length: self::MAX_TITLE)]
    private string $aboutTitle;

    #[ORM\Column(length: self::MAX_ABOUT)]
    private string $aboutText;

    #[ORM\Column(length: self::MAX_TITLE)]
    private string $galleryTitle;

    #[ORM\Column(length: self::MAX_INTRO, options: ['default' => ''])]
    private string $introFr = '';

    #[ORM\Column(length: self::MAX_META_DESCRIPTION, options: ['default' => ''])]
    private string $metaDescriptionFr = '';

    #[ORM\Column(length: self::MAX_SEARCH_TITLE, options: ['default' => ''])]
    private string $searchTitleFr = '';

    #[ORM\Column(length: self::MAX_TITLE, options: ['default' => ''])]
    private string $aboutTitleFr = '';

    #[ORM\Column(length: self::MAX_ABOUT, options: ['default' => ''])]
    private string $aboutTextFr = '';

    #[ORM\Column(length: self::MAX_TITLE, options: ['default' => ''])]
    private string $galleryTitleFr = '';

    #[ORM\Column(length: self::MAX_ANNOUNCEMENT, options: ['default' => ''])]
    private string $announcement = '';

    #[ORM\Column(length: self::MAX_ANNOUNCEMENT, options: ['default' => ''])]
    private string $announcementFr = '';

    #[ORM\Column(length: self::MAX_PRICE_NOTE, options: ['default' => ''])]
    private string $priceNote = '';

    #[ORM\Column(length: self::MAX_PRICE_NOTE, options: ['default' => ''])]
    private string $priceNoteFr = '';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $logoFile = null;

    #[ORM\Column(nullable: true)]
    private ?int $logoWidth = null;

    #[ORM\Column(nullable: true)]
    private ?int $logoHeight = null;

    #[ORM\Column(length: Typeface::MAX_FAMILY, nullable: true)]
    private ?string $headingFontFamily = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $headingFontFile = null;

    #[ORM\Column(nullable: true)]
    private ?int $headingFontWeight = null;

    #[ORM\Column(length: Typeface::MAX_FAMILY, nullable: true)]
    private ?string $bodyFontFamily = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $bodyFontFile = null;

    #[ORM\Column(nullable: true)]
    private ?int $bodyFontWeight = null;

    private function __construct()
    {
        $this->studioName = 'mossyleaf.studio';
        $this->intro = 'Illustrations of little critters among leaves and moss, drawn by hand.';
        $this->metaDescription = 'Illustrations of little critters among leaves and moss, drawn by hand. Prints, stickers and illustrations by mossyleaf.studio.';
        $this->homeNoteTitle = 'The website is still growing';
        $this->homeNoteText = 'New pages are sprouting slowly. Meanwhile, the shop is open on [Etsy](https://www.etsy.com/shop/mossyleafstudio) and new drawings appear on [Instagram](https://www.instagram.com/mossyleaf.studio/).';
        $this->aboutTitle = 'About the studio';
        $this->aboutText = "mossyleaf.studio is a tiny illustration studio. It draws the small animals that live among leaves, ferns and moss, then turns them into prints, stickers and illustrations.\n\nYou can order them on Etsy, or find the studio's table at markets and conventions.";
        $this->galleryTitle = 'Recent drawings';
    }

    public static function initial(): self
    {
        return new self();
    }

    public function editIdentity(string $studioName, string $intro, string $metaDescription, string $searchTitle = '', ?string $introFr = null, ?string $metaDescriptionFr = null, ?string $searchTitleFr = null): void
    {
        $this->studioName = self::text('studio_name', $studioName, self::MAX_STUDIO_NAME);
        $this->intro = self::text('intro', $intro, self::MAX_INTRO);
        $this->metaDescription = self::text('meta_description', $metaDescription, self::MAX_META_DESCRIPTION);
        $this->searchTitle = self::optional('search_title', $searchTitle, self::MAX_SEARCH_TITLE, '');
        $this->introFr = self::optional('intro_fr', $introFr, self::MAX_INTRO, $this->introFr);
        $this->metaDescriptionFr = self::optional('meta_description_fr', $metaDescriptionFr, self::MAX_META_DESCRIPTION, $this->metaDescriptionFr);
        $this->searchTitleFr = self::optional('search_title_fr', $searchTitleFr, self::MAX_SEARCH_TITLE, $this->searchTitleFr);
    }

    public function editShopNotes(string $announcement, string $announcementFr, string $priceNote, string $priceNoteFr): void
    {
        $this->announcement = self::optional('announcement', $announcement, self::MAX_ANNOUNCEMENT, '');
        $this->announcementFr = self::optional('announcement_fr', $announcementFr, self::MAX_ANNOUNCEMENT, '');
        $this->priceNote = self::optional('price_note', $priceNote, self::MAX_PRICE_NOTE, '');
        $this->priceNoteFr = self::optional('price_note_fr', $priceNoteFr, self::MAX_PRICE_NOTE, '');
    }

    public function searchTitle(): string
    {
        return $this->searchTitle;
    }

    public function pageTitle(): string
    {
        return '' === $this->searchTitle ? $this->studioName : $this->searchTitle;
    }

    public function useLogo(Logo $logo): ?Logo
    {
        $previous = $this->logo();
        $this->logoFile = $logo->file;
        $this->logoWidth = $logo->width;
        $this->logoHeight = $logo->height;

        return $previous;
    }

    public function removeLogo(): ?Logo
    {
        $previous = $this->logo();
        $this->logoFile = null;
        $this->logoWidth = null;
        $this->logoHeight = null;

        return $previous;
    }

    public function logo(): ?Logo
    {
        if (null === $this->logoFile || null === $this->logoWidth || null === $this->logoHeight) {
            return null;
        }

        return new Logo($this->logoFile, $this->logoWidth, $this->logoHeight);
    }

    public function editHomeNote(string $title, string $text): void
    {
        $this->homeNoteTitle = self::text('home_note_title', $title, self::MAX_TITLE);
        $this->homeNoteText = self::text('home_note_text', $text, self::MAX_HOME_NOTE);
    }

    public function editAbout(string $title, string $text, ?string $titleFr = null, ?string $textFr = null): void
    {
        $this->aboutTitle = self::text('about_title', $title, self::MAX_TITLE);
        $this->aboutText = self::text('about_text', str_replace("\r\n", "\n", $text), self::MAX_ABOUT);
        $this->aboutTitleFr = self::optional('about_title_fr', $titleFr, self::MAX_TITLE, $this->aboutTitleFr);
        $this->aboutTextFr = self::optional('about_text_fr', null === $textFr ? null : str_replace("\r\n", "\n", $textFr), self::MAX_ABOUT, $this->aboutTextFr);
    }

    public function editGallery(string $title, ?string $titleFr = null): void
    {
        $this->galleryTitle = self::text('gallery_title', $title, self::MAX_TITLE);
        $this->galleryTitleFr = self::optional('gallery_title_fr', $titleFr, self::MAX_TITLE, $this->galleryTitleFr);
    }

    public function useFont(FontRole $role, ?Typeface $typeface): void
    {
        if (FontRole::Heading === $role) {
            $this->headingFontFamily = $typeface?->family;
            $this->headingFontFile = $typeface?->file;
            $this->headingFontWeight = $typeface?->weight;

            return;
        }

        $this->bodyFontFamily = $typeface?->family;
        $this->bodyFontFile = $typeface?->file;
        $this->bodyFontWeight = $typeface?->weight;
    }

    public function font(FontRole $role): ?Typeface
    {
        [$family, $file, $weight] = match ($role) {
            FontRole::Heading => [$this->headingFontFamily, $this->headingFontFile, $this->headingFontWeight],
            FontRole::Body => [$this->bodyFontFamily, $this->bodyFontFile, $this->bodyFontWeight],
        };

        return null === $family || null === $file || null === $weight ? null : new Typeface($family, $file, $weight);
    }

    public function studioName(): string
    {
        return $this->studioName;
    }

    public function intro(): string
    {
        return $this->intro;
    }

    public function metaDescription(): string
    {
        return $this->metaDescription;
    }

    public function homeNoteTitle(): string
    {
        return $this->homeNoteTitle;
    }

    public function homeNoteText(): string
    {
        return $this->homeNoteText;
    }

    public function aboutTitle(): string
    {
        return $this->aboutTitle;
    }

    public function aboutText(): string
    {
        return $this->aboutText;
    }

    /** @return list<string> */
    public function aboutParagraphs(): array
    {
        return self::paragraphs($this->aboutText);
    }

    /** @return list<string> */
    public static function paragraphs(string $text): array
    {
        return array_values(array_filter(array_map(trim(...), preg_split('/\n\s*\n/', $text) ?: []), static fn (string $paragraph): bool => '' !== $paragraph));
    }

    public function galleryTitle(): string
    {
        return $this->galleryTitle;
    }

    public function introFr(): string
    {
        return $this->introFr;
    }

    public function metaDescriptionFr(): string
    {
        return $this->metaDescriptionFr;
    }

    public function searchTitleFr(): string
    {
        return $this->searchTitleFr;
    }

    public function aboutTitleFr(): string
    {
        return $this->aboutTitleFr;
    }

    public function aboutTextFr(): string
    {
        return $this->aboutTextFr;
    }

    public function galleryTitleFr(): string
    {
        return $this->galleryTitleFr;
    }

    public function announcement(): string
    {
        return $this->announcement;
    }

    public function announcementFr(): string
    {
        return $this->announcementFr;
    }

    public function priceNote(): string
    {
        return $this->priceNote;
    }

    public function priceNoteFr(): string
    {
        return $this->priceNoteFr;
    }

    private static function text(string $field, string $value, int $max): string
    {
        $value = trim($value);
        if ('' === $value) {
            throw new EmptyText($field);
        }
        if (mb_strlen($value) > $max) {
            throw new TextTooLong($field, $max);
        }

        return $value;
    }

    private static function optional(string $field, ?string $value, int $max, string $unchanged): string
    {
        if (null === $value) {
            return $unchanged;
        }
        $value = trim($value);
        if (mb_strlen($value) > $max) {
            throw new TextTooLong($field, $max);
        }

        return $value;
    }
}
