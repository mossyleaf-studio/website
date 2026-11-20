<?php

declare(strict_types=1);

namespace App\Presentation\Integration;

use App\Application\Catalogue\IntegrationToken;
use App\Domain\Catalogue\Exception\CatalogueTooLarge;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
final readonly class IntegrationGuard
{
    private const string PATH = '/integrations/';
    private const string SCHEME = 'Bearer ';
    private const int MAX_MEGABYTES = 8;

    public function __construct(
        #[\SensitiveParameter]
        #[Autowire(env: 'MOSSYTRUNK_TOKEN')]
        private string $token,
        #[Target('integration_authentication')]
        private RateLimiterFactoryInterface $failedAuthentications,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with(rawurldecode($request->getPathInfo()), self::PATH)) {
            return;
        }

        if (!IntegrationToken::accepts($this->token)) {
            $event->setResponse(new Response(status: Response::HTTP_NOT_FOUND));

            return;
        }

        $failures = $this->failedAuthentications->create($request->getClientIp());
        $limit = $failures->consume(0);
        if (0 === $limit->getRemainingTokens()) {
            $event->setResponse(new Response(status: Response::HTTP_TOO_MANY_REQUESTS, headers: ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())]));

            return;
        }

        $authorization = $request->headers->get('Authorization') ?? '';
        if (!str_starts_with($authorization, self::SCHEME) || !hash_equals($this->token, substr($authorization, \strlen(self::SCHEME)))) {
            $failures->consume();
            $event->setResponse(new Response(status: Response::HTTP_UNAUTHORIZED, headers: ['WWW-Authenticate' => 'Bearer']));

            return;
        }

        $maxBytes = self::MAX_MEGABYTES * 1024 * 1024;
        if ((int) $request->headers->get('Content-Length') > $maxBytes || \strlen($request->getContent()) > $maxBytes) {
            throw new CatalogueTooLarge('megabytes', self::MAX_MEGABYTES);
        }
    }
}
