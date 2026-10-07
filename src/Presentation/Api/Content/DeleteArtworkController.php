<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\DeleteArtwork\DeleteArtwork;
use App\Application\Content\DeleteArtwork\DeleteArtworkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/artworks/{id}', name: 'api_artworks_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteArtworkController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteArtworkHandler $deleteArtwork): Response
    {
        $deleteArtwork(new DeleteArtwork($id));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
