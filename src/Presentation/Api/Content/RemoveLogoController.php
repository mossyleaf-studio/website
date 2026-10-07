<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\RemoveLogo\RemoveLogo;
use App\Application\Content\RemoveLogo\RemoveLogoHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/logo', name: 'api_logo_remove', methods: ['DELETE'], format: 'json')]
final class RemoveLogoController extends AbstractController
{
    public function __invoke(RemoveLogoHandler $removeLogo): JsonResponse
    {
        return $this->json($removeLogo(new RemoveLogo()));
    }
}
