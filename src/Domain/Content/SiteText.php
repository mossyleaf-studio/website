<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Content\Exception\EmptyText;
use App\Domain\Content\Exception\TextTooLong;
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

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $logoFile = null;

    #[ORM\Column(nullable: true)]
    private ?int $logoWidth = null;

    #[ORM\Column(nullable: true)]
    private ?int $logoHeight = null;

    #[ORM\Column(length: 32, enumType: SiteFont::class, options: ['default' => 'gaegu'])]
    private SiteFont $headingFont = SiteFont::Gaegu;

    #[ORM\Column(length: 32, enumType: SiteFont::class, options: ['default' => 'kalam'])]
    private SiteFont $bodyFont = SiteFont::Kalam;

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

    public function editIdentity(string $studioName, string $intro, string $metaDescription, string $searchTitle = ''): void
    {
        $this->studioName = self::text('studio_name', $studioName, self::MAX_STUDIO_NAME);
        $this->intro = self::text('intro', $intro, self::MAX_INTRO);
        $this->metaDescription = self::text('meta_description', $metaDescription, self::MAX_META_DESCRIPTION);
        $searchTitle = trim($searchTitle);
        if (mb_strlen($searchTitle) > self::MAX_SEARCH_TITLE) {
            throw new TextTooLong('search_title', self::MAX_SEARCH_TITLE);
        }
        $this->searchTitle = $searchTitle;
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

    public function editAbout(string $title, string $text): void
    {
        $this->aboutTitle = self::text('about_title', $title, self::MAX_TITLE);
        $this->aboutText = self::text('about_text', str_replace("\r\n", "\n", $text), self::MAX_ABOUT);
    }

    public function editGallery(string $title): void
    {
        $this->galleryTitle = self::text('gallery_title', $title, self::MAX_TITLE);
    }

    public function chooseFonts(SiteFont $heading, SiteFont $body): void
    {
        $this->headingFont = $heading;
        $this->bodyFont = $body;
    }

    public function headingFont(): SiteFont
    {
        return $this->headingFont;
    }

    public function bodyFont(): SiteFont
    {
        return $this->bodyFont;
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
        return array_values(array_filter(array_map(trim(...), preg_split('/\n\s*\n/', $this->aboutText) ?: []), static fn (string $paragraph): bool => '' !== $paragraph));
    }

    public function galleryTitle(): string
    {
        return $this->galleryTitle;
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
}
