<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\DeleteLink\DeleteLink;
use App\Application\Content\DeleteLink\DeleteLinkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/links/{id}', name: 'api_links_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteLinkController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteLinkHandler $deleteLink): Response
    {
        $deleteLink(new DeleteLink($id));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
