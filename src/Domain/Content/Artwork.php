<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Content\Exception\TextTooLong;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'artwork')]
#[ORM\Index(name: 'artwork_featured', columns: ['featured'])]
class Artwork
{
    public const int MAX_ALT = 200;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 64, unique: true)]
    private string $file;

    #[ORM\Column(length: self::MAX_ALT)]
    private string $alt = '';

    #[ORM\Column]
    private int $width;

    #[ORM\Column]
    private int $height;

    #[ORM\Column]
    private bool $featured = false;

    #[ORM\Column]
    private int $position;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

    private function __construct(Ulid $id, string $file, int $width, int $height, int $position, \DateTimeImmutable $now)
    {
        $this->id = $id;
        $this->file = $file;
        $this->width = $width;
        $this->height = $height;
        $this->position = $position;
        $this->uploadedAt = $now;
    }

    public static function upload(Ulid $id, string $file, int $width, int $height, string $alt, int $position, \DateTimeImmutable $now): self
    {
        $artwork = new self($id, $file, $width, $height, $position, $now);
        $artwork->describe($alt);

        return $artwork;
    }

    public function describe(string $alt): void
    {
        $alt = trim($alt);
        if (mb_strlen($alt) > self::MAX_ALT) {
            throw new TextTooLong('artwork_alt', self::MAX_ALT);
        }
        $this->alt = $alt;
    }

    public function feature(): void
    {
        $this->featured = true;
    }

    public function unfeature(): void
    {
        $this->featured = false;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function alt(): string
    {
        return $this->alt;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function position(): int
    {
        return $this->position;
    }
}
