<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReplaceCatalogue;

use App\Application\AtomicChange;
use App\Application\Transaction;
use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\CategoryRepository;
use App\Domain\Catalogue\Exception\CatalogueTooLarge;
use App\Domain\Catalogue\Exception\DuplicateEntry;
use App\Domain\Catalogue\Exception\EmptyCatalogue;
use App\Domain\Catalogue\Exception\ProductRefused;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductRepository;
use App\Domain\Sales\OrderLine;
use App\Domain\Sales\OrderRepository;
use App\Domain\Shared\Exception\DomainException;
use Psr\Clock\ClockInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class ReplaceCatalogueHandler
{
    private const int MAX_PRODUCTS = 2000;
    private const int MAX_VARIANTS = 50;
    public const string SLUG_LOCALE = 'fr';
    private const string SLUG_FALLBACK = 'product';
    private const int SLUG_SUFFIX_ROOM = 8;

    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
        private SluggerInterface $slugger,
        private ClockInterface $clock,
        private AtomicChange $atomicChange,
        private Transaction $transaction,
        private OrderRepository $orders,
    ) {
    }

    public function __invoke(ReplaceCatalogue $command): CatalogueReport
    {
        $pushed = self::bySourceId($command->products);

        return $this->atomicChange->apply(fn (): CatalogueReport => $this->replace($pushed));
    }

    /**
     * @param list<PushedProduct> $products
     *
     * @return array<string, PushedProduct>
     */
    private static function bySourceId(array $products): array
    {
        if ([] === $products) {
            throw new EmptyCatalogue();
        }
        if (\count($products) > self::MAX_PRODUCTS) {
            throw new CatalogueTooLarge('products', self::MAX_PRODUCTS);
        }

        $byId = $references = $skus = [];
        foreach ($products as $product) {
            $id = (string) $product->id;
            if (isset($byId[$id])) {
                throw new DuplicateEntry('id', $id);
            }
            if (isset($references[$product->reference])) {
                throw new DuplicateEntry('reference', $product->reference);
            }
            $byId[$id] = $product;
            $references[$product->reference] = true;

            if (\count($product->variants) > self::MAX_VARIANTS) {
                throw new CatalogueTooLarge('variants', self::MAX_VARIANTS);
            }
            foreach ($product->variants as $variant) {
                if (isset($skus[$variant->sku])) {
                    throw new DuplicateEntry('sku', $variant->sku);
                }
                $skus[$variant->sku] = true;
            }
        }

        return $byId;
    }

    /**
     * @param array<string, PushedProduct> $pushed
     */
    private function replace(array $pushed): CatalogueReport
    {
        $this->products->lock();

        $now = $this->clock->now();
        $known = $slugs = [];
        $position = $retired = 0;

        foreach ($this->products->all() as $product) {
            $id = (string) $product->sourceId();
            $known[$id] = $product;
            $slugs[$product->slug()] = true;
            $position = max($position, $product->position() + 1);

            if (isset($pushed[$id])) {
                $product->keepVariants(array_map(static fn (PushedVariant $variant): string => $variant->sku, $pushed[$id]->variants));
            } elseif (!$product->isRetired()) {
                $product->retire($now);
                ++$retired;
            }
        }
        $this->transaction->commit();

        $sold = [];
        foreach ($this->orders->unexportedLines() as $line) {
            $sold[$line->sku()][] = $line;
        }
        $categories = [];
        foreach ($this->categories->all() as $category) {
            $categories[$category->name()] = $category;
        }

        foreach ($pushed as $id => $item) {
            try {
                $category = $categories[$item->category] ?? null;
                if (null === $category) {
                    $category = Category::named($item->category, \count($categories));
                    $this->categories->add($category);
                    $categories[$item->category] = $category;
                }

                $product = $known[$id] ?? null;
                if (null === $product) {
                    $product = Product::pushed($item->id, $item->reference, $item->name, $item->displayName, $category, $item->price, $this->freeSlug($item->displayName, $slugs), $position++);
                    $this->products->add($product);
                } else {
                    $product->synchronise($item->reference, $item->name, $item->displayName, $category, $item->price);
                }

                foreach ($item->variants as $index => $variant) {
                    $product->stock($variant->sku, $variant->name, self::unsold($variant->stock, $sold[trim($variant->sku)] ?? []), $index);
                }
            } catch (DomainException $reason) {
                throw new ProductRefused($item->reference, $reason);
            }
        }

        $updated = \count(array_intersect_key($pushed, $known));

        return new CatalogueReport(\count($pushed) - $updated, $updated, $retired);
    }

    /** @param list<OrderLine> $lines */
    private static function unsold(int $pushed, array $lines): int
    {
        if ($pushed < 0) {
            return $pushed;
        }
        foreach ($lines as $line) {
            $pushed -= $line->take($pushed);
        }

        return $pushed;
    }

    /**
     * @param array<string, true> $taken
     */
    private function freeSlug(string $displayName, array &$taken): string
    {
        $base = $this->slugger->slug($displayName, '-', self::SLUG_LOCALE)->lower()->truncate(Product::MAX_SLUG - self::SLUG_SUFFIX_ROOM)->trim('-')->toString();
        $base = '' === $base ? self::SLUG_FALLBACK : $base;

        $slug = $base;
        for ($number = 2; isset($taken[$slug]); ++$number) {
            $slug = $base.'-'.$number;
        }
        $taken[$slug] = true;

        return $slug;
    }
}
