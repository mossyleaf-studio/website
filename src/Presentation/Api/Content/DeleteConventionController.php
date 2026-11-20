<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\DeleteConvention\DeleteConvention;
use App\Application\Content\DeleteConvention\DeleteConventionHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/conventions/{id}', name: 'api_conventions_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteConventionController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteConventionHandler $deleteConvention): Response
    {
        $deleteConvention(new DeleteConvention($id));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
