<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\EditHomeNote\EditHomeNote;
use App\Application\Content\EditHomeNote\EditHomeNoteHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/texts/home-note', name: 'api_texts_home_note', methods: ['PUT'], format: 'json')]
final class EditHomeNoteController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] HomeNotePayload $payload, EditHomeNoteHandler $editHomeNote): JsonResponse
    {
        return $this->json($editHomeNote(new EditHomeNote($payload->title, $payload->text)));
    }
}
