<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\DescribeArtwork\DescribeArtwork;
use App\Application\Content\DescribeArtwork\DescribeArtworkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/artworks/{id}', name: 'api_artworks_describe', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class DescribeArtworkController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] AltPayload $payload, DescribeArtworkHandler $describeArtwork): JsonResponse
    {
        return $this->json($describeArtwork(new DescribeArtwork($id, $payload->alt)));
    }
}
