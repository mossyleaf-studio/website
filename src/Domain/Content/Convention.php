<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Content\Exception\ConventionEndsBeforeStart;
use App\Domain\Shared\Exception\EmptyText;
use App\Domain\Shared\Exception\TextTooLong;
use App\Domain\Shared\Translation;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'convention')]
class Convention
{
    public const int MAX_NAME = 120;
    public const int MAX_PLACE = 120;
    public const int MAX_URL = 500;
    public const int MAX_NOTE = 300;

    private const string DAY = 'Y-m-d';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: self::MAX_NAME)]
    private string $name;

    #[ORM\Column(length: self::MAX_PLACE)]
    private string $place;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startsOn;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $endsOn;

    #[ORM\Column(length: self::MAX_URL, nullable: true)]
    private ?string $url;

    #[ORM\Embedded(class: Translation::class, columnPrefix: 'note_')]
    private Translation $note;

    private function __construct()
    {
        $this->id = new Ulid();
    }

    public static function plan(string $name, string $place, \DateTimeImmutable $startsOn, \DateTimeImmutable $endsOn, ?string $url, Translation $note): self
    {
        $convention = new self();
        $convention->revise($name, $place, $startsOn, $endsOn, $url, $note);

        return $convention;
    }

    public function revise(string $name, string $place, \DateTimeImmutable $startsOn, \DateTimeImmutable $endsOn, ?string $url, Translation $note): void
    {
        $startsOn = $startsOn->setTime(0, 0);
        $endsOn = $endsOn->setTime(0, 0);
        if ($endsOn < $startsOn) {
            throw new ConventionEndsBeforeStart();
        }
        if ($note->exceeds(self::MAX_NOTE)) {
            throw new TextTooLong('convention_note', self::MAX_NOTE);
        }

        $this->name = self::text('convention_name', $name, self::MAX_NAME);
        $this->place = self::text('convention_place', $place, self::MAX_PLACE);
        $this->startsOn = $startsOn;
        $this->endsOn = $endsOn;
        $this->url = self::validUrl($url);
        $this->note = $note;
    }

    public function isOverOn(\DateTimeImmutable $day): bool
    {
        return $this->endsOn->format(self::DAY) < $day->format(self::DAY);
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function place(): string
    {
        return $this->place;
    }

    public function startsOn(): \DateTimeImmutable
    {
        return $this->startsOn;
    }

    public function endsOn(): \DateTimeImmutable
    {
        return $this->endsOn;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    public function note(): Translation
    {
        return $this->note;
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

    private static function validUrl(?string $url): ?string
    {
        $url = trim($url ?? '');

        return '' === $url ? null : HttpsUrl::check($url, self::MAX_URL);
    }
}
