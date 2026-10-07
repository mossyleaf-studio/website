<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\FontLibrary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/fonts', name: 'api_fonts_list', methods: ['GET'], format: 'json')]
final class ListFontFamiliesController extends AbstractController
{
    public function __invoke(FontLibrary $library): JsonResponse
    {
        return $this->json(['families' => $library->families()]);
    }
}
