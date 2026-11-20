<?php

declare(strict_types=1);

namespace App\Presentation\Api\Sales;

use App\Application\Sales\ShipOrder\ShipOrder;
use App\Application\Sales\ShipOrder\ShipOrderHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/orders/{id}/shipment', name: 'api_orders_ship', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class ShipOrderController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] ShipmentPayload $payload, ShipOrderHandler $shipOrder, OrderJson $json): JsonResponse
    {
        return $this->json($json->of($shipOrder(new ShipOrder($id, $payload->trackingNumber))));
    }
}
