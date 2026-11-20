<?php

declare(strict_types=1);

namespace App\Presentation\Api\Sales;

use App\Application\Sales\CancelOrder\CancelOrder;
use App\Application\Sales\CancelOrder\CancelOrderHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/orders/{id}/cancellation', name: 'api_orders_cancel', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class CancelOrderController extends AbstractController
{
    public function __invoke(Ulid $id, CancelOrderHandler $cancelOrder, OrderJson $json): JsonResponse
    {
        return $this->json($json->of($cancelOrder(new CancelOrder($id))));
    }
}
