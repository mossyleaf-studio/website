<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Shared\Exception\TextTooLong;
use App\Domain\Shared\Translation;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'legal_page')]
class LegalPage
{
    public const int MAX_TEXT = 30000;

    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private string $kind;

    #[ORM\Embedded(class: Translation::class, columnPrefix: 'text_')]
    private Translation $text;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    private function __construct(LegalKind $kind)
    {
        $this->kind = $kind->value;
        $this->text = Translation::none();
    }

    public static function blank(LegalKind $kind): self
    {
        return new self($kind);
    }

    public function write(Translation $text, \DateTimeImmutable $now): void
    {
        if ($text->exceeds(self::MAX_TEXT)) {
            throw new TextTooLong('legal_text', self::MAX_TEXT);
        }

        $this->text = $text;
        $this->updatedAt = $now;
    }

    public function kind(): LegalKind
    {
        return LegalKind::from($this->kind);
    }

    public function text(): Translation
    {
        return $this->text;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
