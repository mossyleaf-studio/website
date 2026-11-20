<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\EditShopNotes\EditShopNotes;
use App\Application\Content\EditShopNotes\EditShopNotesHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/texts/shop-notes', name: 'api_texts_shop_notes', methods: ['PUT'], format: 'json')]
final class EditShopNotesController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] ShopNotesPayload $payload, EditShopNotesHandler $editShopNotes): JsonResponse
    {
        return $this->json($editShopNotes(new EditShopNotes($payload->announcement, $payload->announcementFr, $payload->priceNote, $payload->priceNoteFr)));
    }
}
