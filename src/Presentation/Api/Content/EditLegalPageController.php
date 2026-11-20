<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\EditLegalPage\EditLegalPage;
use App\Application\Content\EditLegalPage\EditLegalPageHandler;
use App\Domain\Content\LegalKind;
use App\Presentation\Web\Shop\ShopUrls;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[Route('/api/admin/legal-pages/{kind}', name: 'api_legal_pages_edit', requirements: ['kind' => new EnumRequirement(LegalKind::class)], methods: ['PUT'], format: 'json')]
final class EditLegalPageController extends AbstractController
{
    public function __invoke(LegalKind $kind, #[MapRequestPayload] LegalPagePayload $payload, EditLegalPageHandler $editLegalPage, ShopUrls $urls): JsonResponse
    {
        return $this->json(AdminLegalPage::of($editLegalPage(new EditLegalPage($kind, $payload->text, $payload->textFr)), $urls));
    }
}
