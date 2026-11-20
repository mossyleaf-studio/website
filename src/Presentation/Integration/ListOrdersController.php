<?php

declare(strict_types=1);

namespace App\Presentation\Integration;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/integrations/orders', name: 'integration_orders_list', methods: ['GET'], format: 'json', stateless: true)]
final class ListOrdersController extends AbstractController
{
    public function __invoke(): JsonResponse
    {
        return $this->json(['orders' => []]);
    }
}
