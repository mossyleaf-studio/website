<?php

declare(strict_types=1);

namespace App\Presentation\Integration;

use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Application\Catalogue\ReplaceCatalogue\PushedVariant;
use App\Domain\Catalogue\Product;
use App\Domain\Shared\Translation;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class CatalogueProductPayload
{
    /**
     * @param list<CatalogueVariantPayload> $variants
     * @param array<mixed>                  $descriptions
     */
    public function __construct(
        #[Assert\NotBlank(message: 'id.invalid')]
        #[Assert\Ulid(message: 'id.invalid')]
        public string $id,
        public string $reference,
        public string $name,
        public string $category,
        public string $displayName,
        #[Assert\LessThanOrEqual(Product::MAX_PRICE)]
        public int $price,
        #[Assert\Valid]
        #[Assert\Count(min: 1)]
        public array $variants,
        public array $descriptions = [],
    ) {
    }

    #[Assert\Callback]
    public function descriptionsAreTextsByLanguage(ExecutionContextInterface $context): void
    {
        if (([] !== $this->descriptions && array_is_list($this->descriptions)) || [] !== array_filter($this->descriptions, static fn (mixed $text): bool => !\is_string($text))) {
            $context->buildViolation('descriptions.invalid')->atPath('descriptions')->addViolation();
        }
    }

    public function pushed(): PushedProduct
    {
        return new PushedProduct(
            Ulid::fromString($this->id),
            $this->reference,
            $this->name,
            $this->category,
            $this->displayName,
            $this->price,
            array_map(static fn (CatalogueVariantPayload $variant): PushedVariant => $variant->pushed(), $this->variants),
            Translation::of(self::text($this->descriptions['en'] ?? ''), self::text($this->descriptions['fr'] ?? '')),
        );
    }

    private static function text(mixed $text): string
    {
        return \is_string($text) ? $text : '';
    }
}
