<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
#[Route('/_fake/sumup/{id}', name: 'fake_sumup_page', methods: ['GET'], stateless: true)]
final readonly class FakeSumUpPageController
{
    public function __construct(
        private FakeSumUp $sumUp,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $checkout = $this->sumUp->checkout($id) ?? throw new NotFoundHttpException();

        return new Response($this->twig->render('@fake/sumup.html.twig', [
            'checkout' => $checkout,
            'settle' => FakeSumUpSettleController::SKIP_WEBHOOK === $request->query->getString('webhook') ? ['id' => $id, 'webhook' => FakeSumUpSettleController::SKIP_WEBHOOK] : ['id' => $id],
        ]));
    }
}
