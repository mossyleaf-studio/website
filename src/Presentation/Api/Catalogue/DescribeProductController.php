<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\DescribeProduct\DescribeProduct;
use App\Application\Catalogue\DescribeProduct\DescribeProductHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/products/{id}', name: 'api_products_describe', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class DescribeProductController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] ProductPayload $payload, DescribeProductHandler $describeProduct): JsonResponse
    {
        return $this->json($describeProduct(new DescribeProduct($id, $payload->title, $payload->titleFr, $payload->description, $payload->descriptionFr, $payload->slug)));
    }
}
