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
#[ORM\Table(name: 'category')]
class Category implements Orderable
{
    public const int MAX_NAME = 100;
    public const int MAX_LABEL = 60;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: self::MAX_NAME, unique: true)]
    private string $name;

    #[ORM\Embedded(class: Translation::class, columnPrefix: 'label_')]
    private Translation $label;

    #[ORM\Column]
    private int $position;

    private function __construct(string $name, int $position)
    {
        $this->id = new Ulid();
        $this->name = $name;
        $this->label = Translation::none();
        $this->position = $position;
    }

    public static function named(string $name, int $position): self
    {
        $name = trim($name);
        if ('' === $name) {
            throw new EmptyText('category_name');
        }
        if (mb_strlen($name) > self::MAX_NAME) {
            throw new TextTooLong('category_name', self::MAX_NAME);
        }

        return new self($name, $position);
    }

    public function relabel(Translation $label): void
    {
        if ($label->exceeds(self::MAX_LABEL)) {
            throw new TextTooLong('category_label', self::MAX_LABEL);
        }

        $this->label = $label;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function id(): Ulid
    {
        return $this->id;
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

    public function position(): int
    {
        return $this->position;
    }
}
