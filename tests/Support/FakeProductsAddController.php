<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Catalogue\CategoryRepository;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

#[AsController]
#[Route('/_fake/products/{count}', name: 'fake_products_add', requirements: ['count' => '\d+'], methods: ['POST'], stateless: true)]
final readonly class FakeProductsAddController
{
    public const string PREFIX = 'FAKE-PAGE-';
    private const int MAX = 200;

    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(int $count): Response
    {
        $category = $this->categories->all()[0] ?? throw new NotFoundHttpException();

        if ($count < 1 || $count > self::MAX) {
            throw new NotFoundHttpException();
        }

        $existing = $this->products->all();
        $position = array_reduce($existing, static fn (int $carry, Product $product): int => max($carry, $product->position() + 1), 0);

        for ($number = 1; $number <= $count; ++$number) {
            $label = \sprintf('%s%03d', self::PREFIX, $number);
            $product = Product::pushed(new Ulid(), $label, 'Extra '.$number, 'Extra product '.$number, $category, 1000, strtolower($label), $position++);
            $product->stock($label.'-A', null, 5, 0);
            $product->show();
            $this->products->add($product);
        }

        $this->entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
