<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\UploadProductPhoto\UploadProductPhoto;
use App\Application\Catalogue\UploadProductPhoto\UploadProductPhotoHandler;
use App\Presentation\Api\Content\UploadedImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/products/{id}/photos', name: 'api_product_photos_upload', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class UploadProductPhotoController extends AbstractController
{
    public function __invoke(Ulid $id, Request $request, UploadProductPhotoHandler $uploadProductPhoto): JsonResponse
    {
        return $this->json($uploadProductPhoto(new UploadProductPhoto($id, UploadedImage::pathIn($request))), Response::HTTP_CREATED);
    }
}
