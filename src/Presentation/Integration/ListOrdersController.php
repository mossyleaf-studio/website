<?php

declare(strict_types=1);

namespace App\Presentation\Integration;

use App\Application\Sales\ExportedLine;
use App\Application\Sales\ExportedOrder;
use App\Application\Sales\ExportOrders\ExportOrders;
use App\Application\Sales\ExportOrders\ExportOrdersHandler;
use App\Application\Sales\SettleStaleOrders\SettleStaleOrdersHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/integrations/orders', name: 'integration_orders_list', methods: ['GET'], format: 'json', stateless: true)]
final class ListOrdersController extends AbstractController
{
    public function __invoke(SettleStaleOrdersHandler $settleStaleOrders, ExportOrdersHandler $exportOrders): JsonResponse
    {
        $settleStaleOrders();

        return $this->json(['orders' => array_map(static fn (ExportedOrder $order): array => [
            'id' => $order->reference,
            'reference' => $order->reference,
            'placedAt' => $order->placedAt->format(\DATE_ATOM),
            'lines' => array_map(static fn (ExportedLine $line): array => [
                'sku' => $line->sku,
                'name' => $line->name,
                'variant' => $line->variant,
                'unitPrice' => $line->unitPrice,
                'quantity' => $line->quantity,
            ], $order->lines),
            'goods' => $order->goods,
            'shipping' => $order->shipping,
            'cancelledAt' => $order->cancelledAt?->format(\DATE_ATOM),
        ], $exportOrders(new ExportOrders()))]);
    }
}
