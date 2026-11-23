<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Catalogue\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/_fake/products', name: 'fake_products_remove', methods: ['DELETE'], stateless: true)]
final readonly class FakeProductsRemoveController
{
    public function __construct(private ProductRepository $products, private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(): Response
    {
        foreach ($this->products->all() as $product) {
            if (str_starts_with($product->reference(), FakeProductsAddController::PREFIX)) {
                $this->entityManager->remove($product);
            }
        }

        $this->entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
