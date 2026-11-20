<?php

declare(strict_types=1);

namespace App\Presentation\Api\Sales;

use App\Application\Sales\CheckPayment\CheckPayment;
use App\Application\Sales\CheckPayment\CheckPaymentHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/orders/{id}/payment-check', name: 'api_orders_check_payment', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class CheckPaymentController extends AbstractController
{
    public function __invoke(Ulid $id, CheckPaymentHandler $checkPayment, OrderJson $json): JsonResponse
    {
        return $this->json($json->of($checkPayment(new CheckPayment($id))));
    }
}
