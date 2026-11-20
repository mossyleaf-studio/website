<?php

declare(strict_types=1);

namespace App\Presentation\Integration;

use App\Domain\Catalogue\Exception\CatalogueTooLarge;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
final readonly class IntegrationGuard
{
    private const string PATH = '/integrations/';
    private const string SCHEME = 'Bearer ';
    private const int MIN_TOKEN_LENGTH = 32;
    private const int MAX_MEGABYTES = 8;

    public function __construct(
        #[\SensitiveParameter]
        #[Autowire(env: 'MOSSYTRUNK_TOKEN')]
        private string $token,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with(rawurldecode($request->getPathInfo()), self::PATH)) {
            return;
        }

        if (\strlen($this->token) < self::MIN_TOKEN_LENGTH) {
            $event->setResponse(new Response(status: Response::HTTP_NOT_FOUND));

            return;
        }

        $authorization = $request->headers->get('Authorization') ?? '';
        if (!str_starts_with($authorization, self::SCHEME) || !hash_equals($this->token, substr($authorization, \strlen(self::SCHEME)))) {
            $event->setResponse(new Response(status: Response::HTTP_UNAUTHORIZED, headers: ['WWW-Authenticate' => 'Bearer']));

            return;
        }

        $maxBytes = self::MAX_MEGABYTES * 1024 * 1024;
        if ((int) $request->headers->get('Content-Length') > $maxBytes || \strlen($request->getContent()) > $maxBytes) {
            throw new CatalogueTooLarge('megabytes', self::MAX_MEGABYTES);
        }
    }
}
