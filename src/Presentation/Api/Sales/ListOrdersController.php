<?php

declare(strict_types=1);

namespace App\Presentation\Api\Sales;

use App\Application\Sales\OrderQueries;
use App\Application\Sales\OrderView;
use App\Application\Sales\SettleStaleOrders\SettleStaleOrdersHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/orders', name: 'api_orders_list', methods: ['GET'], format: 'json')]
final class ListOrdersController extends AbstractController
{
    public function __invoke(SettleStaleOrdersHandler $settleStaleOrders, OrderQueries $queries, OrderJson $json): JsonResponse
    {
        $settleStaleOrders();

        return $this->json(array_map(static fn (OrderView $order): array => $json->of($order), $queries->orders()));
    }
}
