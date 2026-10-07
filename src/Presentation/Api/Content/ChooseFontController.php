<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\ChooseFont\ChooseFont;
use App\Application\Content\ChooseFont\ChooseFontHandler;
use App\Domain\Content\FontRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/texts/fonts/{role}', name: 'api_texts_font', requirements: ['role' => 'heading|body'], methods: ['PUT'], format: 'json')]
final class ChooseFontController extends AbstractController
{
    public function __invoke(FontRole $role, #[MapRequestPayload] FontPayload $payload, ChooseFontHandler $chooseFont): JsonResponse
    {
        return $this->json($chooseFont(new ChooseFont($role, $payload->family)));
    }
}
