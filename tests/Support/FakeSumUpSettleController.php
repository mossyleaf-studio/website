<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/_fake/sumup/{id}', name: 'fake_sumup_settle', methods: ['POST'], stateless: true)]
final readonly class FakeSumUpSettleController
{
    public const string SKIP_WEBHOOK = '0';
    public const string PAY = 'pay';

    public function __construct(
        private FakeSumUp $sumUp,
        private HttpKernelInterface $kernel,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $checkout = $this->sumUp->checkout($id) ?? throw new NotFoundHttpException();
        $this->sumUp->settle($id, self::PAY === $request->request->getString('outcome') ? FakeSumUp::PAID : FakeSumUp::FAILED);

        if (self::SKIP_WEBHOOK !== $request->query->getString('webhook')) {
            $this->kernel->handle(Request::create(
                (string) parse_url(\is_string($checkout['return_url'] ?? null) ? $checkout['return_url'] : '', \PHP_URL_PATH),
                'POST',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['event_type' => 'CHECKOUT_STATUS_CHANGED', 'id' => $id], \JSON_THROW_ON_ERROR),
            ), HttpKernelInterface::SUB_REQUEST);
        }

        return new RedirectResponse(\is_string($checkout['redirect_url'] ?? null) ? $checkout['redirect_url'] : '/');
    }
}
