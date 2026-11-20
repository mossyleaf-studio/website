<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Translation
{
    private function __construct(
        #[ORM\Column(type: Types::TEXT)]
        public string $en,
        #[ORM\Column(type: Types::TEXT)]
        public string $fr,
    ) {
    }

    public static function of(string $en, string $fr): self
    {
        return new self(trim($en), trim($fr));
    }

    public static function none(): self
    {
        return new self('', '');
    }

    public function in(Locale $locale): string
    {
        [$own, $other] = Locale::En === $locale ? [$this->en, $this->fr] : [$this->fr, $this->en];

        return '' === $own ? $other : $own;
    }

    public function exceeds(int $max): bool
    {
        return max(mb_strlen($this->en), mb_strlen($this->fr)) > $max;
    }
}
