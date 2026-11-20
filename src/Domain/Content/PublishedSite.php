<?php

declare(strict_types=1);

namespace App\Domain\Content;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'published_site')]
class PublishedSite
{
    private const int ID = 1;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shopContent;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $files;

    #[ORM\Column(length: 16, enumType: PublicPage::class)]
    private PublicPage $page;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $publishedAt;

    #[ORM\Column(length: 60)]
    private string $publishedBy;

    /** @param list<string> $files */
    private function __construct(string $content, string $shopContent, array $files, PublicPage $page, string $publishedBy, \DateTimeImmutable $now)
    {
        $this->content = $content;
        $this->shopContent = $shopContent;
        $this->files = $files;
        $this->page = $page;
        $this->publishedBy = $publishedBy;
        $this->publishedAt = $now;
    }

    /** @param list<string> $files */
    public static function first(string $content, string $shopContent, array $files, PublicPage $page, string $publishedBy, \DateTimeImmutable $now): self
    {
        return new self($content, $shopContent, $files, $page, $publishedBy, $now);
    }

    /** @param list<string> $files */
    public function replace(string $content, string $shopContent, array $files, PublicPage $page, string $publishedBy, \DateTimeImmutable $now): void
    {
        $this->content = $content;
        $this->shopContent = $shopContent;
        $this->files = $files;
        $this->page = $page;
        $this->publishedBy = $publishedBy;
        $this->publishedAt = $now;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function shopContent(): ?string
    {
        return $this->shopContent;
    }

    /** @return list<string> */
    public function files(): array
    {
        return $this->files;
    }

    public function page(): PublicPage
    {
        return $this->page;
    }

    public function publishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function publishedBy(): string
    {
        return $this->publishedBy;
    }
}
