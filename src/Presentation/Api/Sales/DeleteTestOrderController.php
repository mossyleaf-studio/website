<?php

declare(strict_types=1);

namespace App\Presentation\Api\Sales;

use App\Application\Sales\DeleteTestOrder\DeleteTestOrder;
use App\Application\Sales\DeleteTestOrder\DeleteTestOrderHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/orders/{id}', name: 'api_orders_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteTestOrderController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteTestOrderHandler $deleteTestOrder): Response
    {
        $deleteTestOrder(new DeleteTestOrder($id));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
