<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use App\Domain\Shared\Exception\EmptyText;
use App\Domain\Shared\Exception\TextTooLong;
use App\Domain\Shared\Locale;
use App\Domain\Shared\Orderable;
use App\Domain\Shared\Translation;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'collection')]
class Collection implements Orderable
{
    public const int MAX_NAME = 255;
    public const int MAX_LABEL = Category::MAX_LABEL;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $sourceId;

    #[ORM\Column(length: self::MAX_NAME)]
    private string $name;

    #[ORM\Embedded(class: Translation::class, columnPrefix: 'label_')]
    private Translation $label;

    #[ORM\Column]
    private bool $shown = false;

    #[ORM\Column]
    private int $position;

    private function __construct(Ulid $sourceId, string $name, int $position)
    {
        $this->id = new Ulid();
        $this->sourceId = $sourceId;
        $this->label = Translation::none();
        $this->position = $position;
        $this->rename($name);
    }

    public static function pushed(Ulid $sourceId, string $name, int $position): self
    {
        return new self($sourceId, $name, $position);
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new EmptyText('collection_name');
        }
        if (mb_strlen($name) > self::MAX_NAME) {
            throw new TextTooLong('collection_name', self::MAX_NAME);
        }

        $this->name = $name;
    }

    public function relabel(Translation $label): void
    {
        if ($label->exceeds(self::MAX_LABEL)) {
            throw new TextTooLong('collection_label', self::MAX_LABEL);
        }

        $this->label = $label;
    }

    public function show(): void
    {
        $this->shown = true;
    }

    public function hide(): void
    {
        $this->shown = false;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function sourceId(): Ulid
    {
        return $this->sourceId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function label(): Translation
    {
        return $this->label;
    }

    public function labelIn(Locale $locale): string
    {
        $label = $this->label->in($locale);

        return '' === $label ? $this->name : $label;
    }

    public function isShown(): bool
    {
        return $this->shown;
    }

    public function position(): int
    {
        return $this->position;
    }
}
