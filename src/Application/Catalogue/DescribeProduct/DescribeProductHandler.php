<?php

declare(strict_types=1);

namespace App\Application\Catalogue\DescribeProduct;

use App\Application\AtomicChange;
use App\Application\Catalogue\ProductView;
use App\Application\Transaction;
use App\Domain\Catalogue\Exception\SlugTaken;
use App\Domain\Catalogue\ProductRepository;
use App\Domain\Shared\Translation;

final readonly class DescribeProductHandler
{
    public function __construct(
        private ProductRepository $products,
        private Transaction $transaction,
        private AtomicChange $atomicChange,
    ) {
    }

    public function __invoke(DescribeProduct $command): ProductView
    {
        return $this->atomicChange->apply(fn (): ProductView => $this->describe($command));
    }

    private function describe(DescribeProduct $command): ProductView
    {
        $product = $this->products->get($command->id);
        $slug = trim($command->slug);
        $this->products->lock();
        $owner = $this->products->withSlug($slug);
        if (null !== $owner && $owner !== $product) {
            throw new SlugTaken($slug);
        }

        $product->useSlug($slug);
        $product->describe(Translation::of($command->title, $command->titleFr), Translation::of($command->description, $command->descriptionFr));
        $this->transaction->commit();

        return ProductView::of($product);
    }
}
