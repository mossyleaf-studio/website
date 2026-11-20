<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\PublishSite\PublishSite;
use App\Application\Content\PublishSite\PublishSiteHandler;
use App\Presentation\Web\Shop\ShopUrls;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/publication', name: 'api_publication_publish', methods: ['POST'], format: 'json')]
final class PublishSiteController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] PublicationPayload $payload, PublishSiteHandler $publishSite, ShopUrls $urls): JsonResponse
    {
        return $this->json(AdminPublication::of($publishSite(new PublishSite($payload->page)), $urls));
    }
}
