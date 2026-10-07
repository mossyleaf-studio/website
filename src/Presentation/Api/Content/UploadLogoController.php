<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\UploadLogo\UploadLogo;
use App\Application\Content\UploadLogo\UploadLogoHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/logo', name: 'api_logo_upload', methods: ['POST'], format: 'json')]
final class UploadLogoController extends AbstractController
{
    public function __invoke(Request $request, UploadLogoHandler $uploadLogo): JsonResponse
    {
        return $this->json($uploadLogo(new UploadLogo(UploadedImage::pathIn($request))));
    }
}
