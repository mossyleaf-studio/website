<?php

declare(strict_types=1);

namespace App\Presentation\Webhook;

use App\Domain\Sales\Order;
use App\Presentation\VerifyPayment;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/webhooks/sumup', name: 'webhook_sumup', methods: ['POST'], stateless: true)]
final readonly class SumUpWebhookController
{
    public const string EVENT = 'CHECKOUT_STATUS_CHANGED';
    public const int MAX_BYTES = 4096;

    public function __construct(
        private VerifyPayment $verifyPayment,
        #[Target('webhook')]
        private RateLimiterFactoryInterface $limiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $id = $this->limiter->create($request->getClientIp())->consume()->isAccepted() ? self::checkoutId($request) : null;
        if (null !== $id) {
            $this->verifyPayment->verify($id);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private static function checkoutId(Request $request): ?string
    {
        $body = (int) $request->headers->get('Content-Length') > self::MAX_BYTES ? false : stream_get_contents($request->getContent(true), self::MAX_BYTES + 1);
        if (!\is_string($body) || \strlen($body) > self::MAX_BYTES) {
            return null;
        }

        $event = json_decode($body, true);
        $id = \is_array($event) && self::EVENT === ($event['event_type'] ?? null) ? ($event['id'] ?? null) : null;

        return \is_string($id) && '' !== $id && \strlen($id) <= Order::MAX_PAYMENT_ID ? $id : null;
    }
}
