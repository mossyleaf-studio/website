<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\UploadArtwork\UploadArtwork;
use App\Application\Content\UploadArtwork\UploadArtworkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/artworks', name: 'api_artworks_upload', methods: ['POST'], format: 'json')]
final class UploadArtworkController extends AbstractController
{
    public function __invoke(Request $request, UploadArtworkHandler $uploadArtwork): JsonResponse
    {
        return $this->json($uploadArtwork(new UploadArtwork(UploadedImage::pathIn($request), $request->request->getString('alt'))), Response::HTTP_CREATED);
    }
}
