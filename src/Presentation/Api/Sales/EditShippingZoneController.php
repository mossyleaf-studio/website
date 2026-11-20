<?php

declare(strict_types=1);

namespace App\Presentation\Api\Sales;

use App\Application\Sales\EditShippingZone\EditShippingZone;
use App\Application\Sales\EditShippingZone\EditShippingZoneHandler;
use App\Domain\Sales\Zone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/shipping-zones/{code}', name: 'api_shipping_zones_edit', requirements: ['code' => 'france|eu|world'], methods: ['PUT'], format: 'json')]
final class EditShippingZoneController extends AbstractController
{
    public function __invoke(Zone $code, #[MapRequestPayload] ShippingZonePayload $payload, EditShippingZoneHandler $editShippingZone): JsonResponse
    {
        return $this->json($editShippingZone(new EditShippingZone($code, $payload->price, $payload->freeFrom, $payload->enabled)));
    }
}
