<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use App\Domain\Catalogue\Exception\InvalidSlug;
use App\Domain\Catalogue\Exception\NegativeAmount;
use App\Domain\Catalogue\Exception\ProductRetired;
use App\Domain\Shared\Exception\EmptyText;
use App\Domain\Shared\Exception\NotFound;
use App\Domain\Shared\Exception\TextTooLong;
use App\Domain\Shared\Orderable;
use App\Domain\Shared\Ordering;
use App\Domain\Shared\Translation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'product')]
class Product implements Orderable
{
    public const int MAX_REFERENCE = 64;
    public const int MAX_NAME = 255;
    public const int MAX_DISPLAY_NAME = 356;
    public const int MAX_PRICE = 2_147_483_647;
    public const int MAX_SLUG = 80;
    public const int MAX_TITLE = 120;
    public const int MAX_DESCRIPTION = 4000;
    private const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $sourceId;

    #[ORM\Column(length: self::MAX_REFERENCE)]
    private string $reference;

    #[ORM\Column(length: self::MAX_NAME)]
    private string $name;

    #[ORM\Column(length: self::MAX_DISPLAY_NAME)]
    private string $displayName;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Category $category;

    #[ORM\ManyToOne(targetEntity: Collection::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Collection $collection = null;

    #[ORM\Column]
    private int $price;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $retiredAt = null;

    #[ORM\Column(length: self::MAX_SLUG, unique: true)]
    private string $slug;

    #[ORM\Embedded(class: Translation::class, columnPrefix: 'title_')]
    private Translation $title;

    #[ORM\Embedded(class: Translation::class, columnPrefix: 'description_')]
    private Translation $description;

    #[ORM\Column]
    private bool $visible = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $picked = false;

    #[ORM\Column]
    private int $position;

    /** @var DoctrineCollection<int, ProductVariant> */
    #[ORM\OneToMany(targetEntity: ProductVariant::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    private DoctrineCollection $variants;

    /** @var DoctrineCollection<int, ProductPhoto> */
    #[ORM\OneToMany(targetEntity: ProductPhoto::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    private DoctrineCollection $photos;

    private function __construct(Ulid $sourceId, string $slug, int $position)
    {
        $this->id = new Ulid();
        $this->sourceId = $sourceId;
        $this->slug = self::text('product_slug', $slug, self::MAX_SLUG);
        $this->title = Translation::none();
        $this->description = Translation::none();
        $this->position = $position;
        $this->variants = new ArrayCollection();
        $this->photos = new ArrayCollection();
    }

    public static function pushed(Ulid $sourceId, string $reference, string $name, string $displayName, Category $category, int $price, string $slug, int $position): self
    {
        $product = new self($sourceId, $slug, $position);
        $product->synchronise($reference, $name, $displayName, $category, $price);

        return $product;
    }

    public function synchronise(string $reference, string $name, string $displayName, Category $category, int $price): void
    {
        if ($price < 0) {
            throw new NegativeAmount('price');
        }

        $this->reference = self::text('product_reference', $reference, self::MAX_REFERENCE);
        $this->name = self::text('product_name', $name, self::MAX_NAME);
        $this->displayName = self::text('product_display_name', $displayName, self::MAX_DISPLAY_NAME);
        $this->category = $category;
        $this->price = $price;
        $this->retiredAt = null;
    }

    public function belongTo(?Collection $collection): void
    {
        $this->collection = $collection;
    }

    public function stock(string $sku, ?string $name, int $stock, int $position): void
    {
        $sku = trim($sku);
        $variant = $this->variants->findFirst(static fn (int $key, ProductVariant $variant): bool => $variant->sku() === $sku);

        if (null === $variant) {
            $this->variants->add(new ProductVariant($this, $sku, $name, $stock, $position));

            return;
        }

        $variant->rewrite($name, $stock, $position);
    }

    /**
     * @param list<string> $skus
     */
    public function keepVariants(array $skus): void
    {
        foreach ($this->variants->toArray() as $variant) {
            if (!\in_array($variant->sku(), $skus, true)) {
                $this->variants->removeElement($variant);
            }
        }
    }

    public function retire(\DateTimeImmutable $at): void
    {
        $this->retiredAt ??= $at;
        $this->keepVariants([]);
    }

    public function describe(Translation $title, Translation $description): void
    {
        if ($title->exceeds(self::MAX_TITLE)) {
            throw new TextTooLong('product_title', self::MAX_TITLE);
        }
        if ($description->exceeds(self::MAX_DESCRIPTION)) {
            throw new TextTooLong('product_description', self::MAX_DESCRIPTION);
        }

        $this->title = $title;
        $this->description = $description;
    }

    public function prefillDescription(Translation $pushed): void
    {
        $this->description = Translation::of(
            '' === $this->description->en ? self::fit($pushed->en, self::MAX_DESCRIPTION) : $this->description->en,
            '' === $this->description->fr ? self::fit($pushed->fr, self::MAX_DESCRIPTION) : $this->description->fr,
        );
    }

    public function useSlug(string $slug): void
    {
        $slug = trim($slug);
        if (mb_strlen($slug) > self::MAX_SLUG || 1 !== preg_match(self::SLUG_PATTERN, $slug)) {
            throw new InvalidSlug(self::MAX_SLUG);
        }

        $this->slug = $slug;
    }

    public function show(): void
    {
        if ($this->isRetired()) {
            throw new ProductRetired($this->reference);
        }

        $this->visible = true;
    }

    public function hide(): void
    {
        $this->visible = false;
    }

    public function pick(): void
    {
        if ($this->isRetired()) {
            throw new ProductRetired($this->reference);
        }

        $this->picked = true;
    }

    public function unpick(): void
    {
        $this->picked = false;
    }

    public function addPhoto(Ulid $id, PhotoFile $full, PhotoFile $thumb): ProductPhoto
    {
        $last = array_last($this->photos());
        $photo = new ProductPhoto($this, $id, $full, $thumb, null === $last ? 0 : $last->position() + 1);
        $this->photos->add($photo);

        return $photo;
    }

    public function removePhoto(Ulid $id): void
    {
        $photo = $this->photos->findFirst(static fn (int $key, ProductPhoto $photo): bool => $photo->id()->equals($id)) ?? throw new NotFound('photo', (string) $id);
        $this->photos->removeElement($photo);
    }

    /**
     * @param list<string> $orderedIds
     */
    public function reorderPhotos(array $orderedIds): void
    {
        Ordering::apply($this->photos(), $orderedIds);
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

    public function reference(): string
    {
        return $this->reference;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function category(): Category
    {
        return $this->category;
    }

    public function collection(): ?Collection
    {
        return $this->collection;
    }

    public function price(): int
    {
        return $this->price;
    }

    public function isRetired(): bool
    {
        return null !== $this->retiredAt;
    }

    public function retiredAt(): ?\DateTimeImmutable
    {
        return $this->retiredAt;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function title(): Translation
    {
        return $this->title;
    }

    public function description(): Translation
    {
        return $this->description;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function isPicked(): bool
    {
        return $this->picked;
    }

    public function position(): int
    {
        return $this->position;
    }

    /**
     * @return list<ProductVariant>
     */
    public function variants(): array
    {
        $variants = array_values($this->variants->toArray());
        usort($variants, static fn (ProductVariant $a, ProductVariant $b): int => $a->position() <=> $b->position());

        return $variants;
    }

    /**
     * @return list<ProductPhoto>
     */
    public function photos(): array
    {
        $photos = array_values($this->photos->toArray());
        usort($photos, static fn (ProductPhoto $a, ProductPhoto $b): int => $a->position() <=> $b->position());

        return $photos;
    }

    private static function fit(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $head = mb_substr($text, 0, $max + 1);
        foreach (['/^(.*\S)\s*\n\s*\n/su', '/^(.*[.!?…])\s/su', '/^(.*\S)\s/su'] as $cut) {
            if (1 === preg_match($cut, $head, $match)) {
                return $match[1];
            }
        }

        return mb_substr($text, 0, $max);
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
