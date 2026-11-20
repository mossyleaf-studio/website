<?php

declare(strict_types=1);

namespace App\Presentation\Api\Sales;

use App\Application\Sales\ShippingZoneQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/shipping-zones', name: 'api_shipping_zones_list', methods: ['GET'], format: 'json')]
final class ListShippingZonesController extends AbstractController
{
    public function __invoke(ShippingZoneQueries $queries): JsonResponse
    {
        return $this->json($queries->all());
    }
}
