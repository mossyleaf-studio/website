<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use App\Presentation\Api\ProblemResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 15)]
final readonly class ShopApiBodyGuard
{
    public const int MAX_BYTES = 64 * 1024;

    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!ShopApiDoor::isShopApi($request)) {
            return;
        }

        if ((int) $request->headers->get('Content-Length') > self::MAX_BYTES || \strlen($request->getContent()) > self::MAX_BYTES) {
            $event->setResponse(new ProblemResponse($this->translator->trans('problem.too_large.title'), Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $this->translator->trans('problem.too_large.detail'), 'problem.too_large'));
        }
    }
}
