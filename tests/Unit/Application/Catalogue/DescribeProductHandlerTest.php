<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Catalogue;

use App\Application\AtomicChange;
use App\Application\Catalogue\DescribeProduct\DescribeProduct;
use App\Application\Catalogue\DescribeProduct\DescribeProductHandler;
use App\Application\Transaction;
use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\Exception\SlugTaken;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductRepository;
use App\Domain\Shared\Exception\NotFound;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class DescribeProductHandlerTest extends TestCase
{
    public function testASlugTakenByAWriterThatHeldTheCatalogueLockIsRefused(): void
    {
        $prints = Category::named('Prints', 0);
        $fox = Product::pushed(new Ulid(), 'PRT-FOX', 'Fox', 'Prints Fox', $prints, 1500, 'prints-fox', 0);
        $owl = Product::pushed(new Ulid(), 'PRT-OWL', 'Owl', 'Prints Owl', $prints, 1500, 'prints-owl', 1);
        $atomicChange = new class implements AtomicChange {
            public bool $inside = false;

            public function apply(callable $change): mixed
            {
                $this->inside = true;
                try {
                    return $change();
                } finally {
                    $this->inside = false;
                }
            }
        };
        $products = new class($atomicChange, [$fox, $owl], static fn () => $owl->useSlug('fox-print')) implements ProductRepository {
            /**
             * @param list<Product>    $products
             * @param \Closure(): void $otherWriter
             */
            public function __construct(private readonly object $atomicChange, private readonly array $products, private readonly \Closure $otherWriter)
            {
            }

            public function lock(): void
            {
                if (!property_exists($this->atomicChange, 'inside') || true !== $this->atomicChange->inside) {
                    throw new \LogicException('LOCK TABLE can only be used in transaction blocks');
                }
                ($this->otherWriter)();
            }

            public function add(Product $product): void
            {
            }

            public function get(Ulid $id): Product
            {
                foreach ($this->products as $product) {
                    if ($product->id()->equals($id)) {
                        return $product;
                    }
                }
                throw new NotFound('product', (string) $id);
            }

            public function withSlug(string $slug): ?Product
            {
                foreach ($this->products as $product) {
                    if ($product->slug() === $slug) {
                        return $product;
                    }
                }

                return null;
            }

            public function all(): array
            {
                return $this->products;
            }

            public function forSale(bool $hiddenToo): array
            {
                return $this->products;
            }

            public function photoFiles(): array
            {
                return [];
            }
        };
        $transaction = new class implements Transaction {
            public function commit(): void
            {
            }
        };

        $this->expectException(SlugTaken::class);

        (new DescribeProductHandler($products, $transaction, $atomicChange))(new DescribeProduct($fox->id(), 'Fox print', '', '', '', 'fox-print'));
    }
}
