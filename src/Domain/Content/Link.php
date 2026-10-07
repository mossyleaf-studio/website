<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Content\Exception\EmptyText;
use App\Domain\Content\Exception\InvalidLinkUrl;
use App\Domain\Content\Exception\TextTooLong;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'link')]
class Link
{
    public const int MAX_TITLE = 60;
    public const int MAX_DESCRIPTION = 120;
    public const int MAX_URL = 500;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: self::MAX_TITLE)]
    private string $title;

    #[ORM\Column(length: self::MAX_DESCRIPTION)]
    private string $description;

    #[ORM\Column(length: self::MAX_URL)]
    private string $url;

    #[ORM\Column(length: 16, enumType: TapeTone::class)]
    private TapeTone $tape;

    #[ORM\Column]
    private int $position;

    private function __construct(int $position)
    {
        $this->id = new Ulid();
        $this->position = $position;
    }

    public static function create(string $title, string $description, string $url, TapeTone $tape, int $position): self
    {
        $link = new self($position);
        $link->edit($title, $description, $url, $tape);

        return $link;
    }

    public function edit(string $title, string $description, string $url, TapeTone $tape): void
    {
        $this->title = self::text('link_title', $title, self::MAX_TITLE);
        $this->description = self::text('link_description', $description, self::MAX_DESCRIPTION);
        $this->url = self::validUrl($url);
        $this->tape = $tape;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function tape(): TapeTone
    {
        return $this->tape;
    }

    public function position(): int
    {
        return $this->position;
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

    private static function validUrl(string $url): string
    {
        $url = trim($url);
        if (mb_strlen($url) > self::MAX_URL || 'https' !== parse_url($url, \PHP_URL_SCHEME) || false === filter_var($url, \FILTER_VALIDATE_URL)) {
            throw new InvalidLinkUrl($url);
        }

        return $url;
    }
}
