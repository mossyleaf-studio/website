<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\FeatureArtwork\FeatureArtwork;
use App\Application\Content\FeatureArtwork\FeatureArtworkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/artworks/{id}/featured', name: 'api_artworks_feature', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class FeatureArtworkController extends AbstractController
{
    public function __invoke(Ulid $id, FeatureArtworkHandler $featureArtwork): JsonResponse
    {
        return $this->json($featureArtwork(new FeatureArtwork($id)));
    }
}
