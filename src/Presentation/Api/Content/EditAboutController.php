<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\EditAbout\EditAbout;
use App\Application\Content\EditAbout\EditAboutHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/texts/about', name: 'api_texts_about', methods: ['PUT'], format: 'json')]
final class EditAboutController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] AboutPayload $payload, EditAboutHandler $editAbout): JsonResponse
    {
        return $this->json($editAbout(new EditAbout($payload->title, $payload->text)));
    }
}
