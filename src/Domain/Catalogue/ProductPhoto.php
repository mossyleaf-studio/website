<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use App\Domain\Shared\Orderable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'product_photo')]
class ProductPhoto implements Orderable
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(length: 64, unique: true)]
    private string $file;

    #[ORM\Column]
    private int $width;

    #[ORM\Column]
    private int $height;

    #[ORM\Column(length: 64, unique: true)]
    private string $thumbFile;

    #[ORM\Column]
    private int $thumbWidth;

    #[ORM\Column]
    private int $thumbHeight;

    #[ORM\Column]
    private int $position;

    public function __construct(Product $product, Ulid $id, PhotoFile $full, PhotoFile $thumb, int $position)
    {
        $this->id = $id;
        $this->product = $product;
        $this->file = $full->file;
        $this->width = $full->width;
        $this->height = $full->height;
        $this->thumbFile = $thumb->file;
        $this->thumbWidth = $thumb->width;
        $this->thumbHeight = $thumb->height;
        $this->position = $position;
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

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function thumbFile(): string
    {
        return $this->thumbFile;
    }

    public function thumbWidth(): int
    {
        return $this->thumbWidth;
    }

    public function thumbHeight(): int
    {
        return $this->thumbHeight;
    }

    public function position(): int
    {
        return $this->position;
    }
}
