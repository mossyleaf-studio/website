<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\ChooseFonts\ChooseFonts;
use App\Application\Content\ChooseFonts\ChooseFontsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/texts/fonts', name: 'api_texts_fonts', methods: ['PUT'], format: 'json')]
final class ChooseFontsController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] FontsPayload $payload, ChooseFontsHandler $chooseFonts): JsonResponse
    {
        return $this->json($chooseFonts(new ChooseFonts($payload->heading, $payload->body)));
    }
}
