<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\DeleteProductPhoto\DeleteProductPhoto;
use App\Application\Catalogue\DeleteProductPhoto\DeleteProductPhotoHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/products/{id}/photos/{photo}', name: 'api_product_photos_delete', requirements: ['id' => Requirement::ULID, 'photo' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteProductPhotoController extends AbstractController
{
    public function __invoke(Ulid $id, Ulid $photo, DeleteProductPhotoHandler $deleteProductPhoto): Response
    {
        $deleteProductPhoto(new DeleteProductPhoto($id, $photo));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
