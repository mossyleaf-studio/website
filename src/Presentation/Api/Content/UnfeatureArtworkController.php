<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\UnfeatureArtwork\UnfeatureArtwork;
use App\Application\Content\UnfeatureArtwork\UnfeatureArtworkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/artworks/{id}/featured', name: 'api_artworks_unfeature', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class UnfeatureArtworkController extends AbstractController
{
    public function __invoke(Ulid $id, UnfeatureArtworkHandler $unfeatureArtwork): JsonResponse
    {
        return $this->json($unfeatureArtwork(new UnfeatureArtwork($id)));
    }
}
