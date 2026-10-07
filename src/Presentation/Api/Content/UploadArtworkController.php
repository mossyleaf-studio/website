<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\UploadArtwork\UploadArtwork;
use App\Application\Content\UploadArtwork\UploadArtworkHandler;
use App\Domain\Content\Exception\ImageTooLarge;
use App\Domain\Content\Exception\UnsupportedImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/artworks', name: 'api_artworks_upload', methods: ['POST'], format: 'json')]
final class UploadArtworkController extends AbstractController
{
    public function __invoke(Request $request, UploadArtworkHandler $uploadArtwork): JsonResponse
    {
        $file = $request->files->get('image');
        if ($file instanceof UploadedFile && \UPLOAD_ERR_INI_SIZE === $file->getError()) {
            throw new ImageTooLarge(ImageTooLarge::MAX_MEGABYTES);
        }
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new UnsupportedImage();
        }
        if ($file->getSize() > ImageTooLarge::MAX_MEGABYTES * 1024 * 1024) {
            throw new ImageTooLarge(ImageTooLarge::MAX_MEGABYTES);
        }

        return $this->json($uploadArtwork(new UploadArtwork($file->getPathname(), $request->request->getString('alt'))), Response::HTTP_CREATED);
    }
}
