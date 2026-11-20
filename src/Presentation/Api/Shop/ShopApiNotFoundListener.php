<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use App\Presentation\Api\ProblemResponse;
use App\Presentation\Web\Shop\ShopDoor;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final readonly class ShopApiNotFoundListener
{
    private const string PUBLIC_PATH = '/api/shop/';

    public function __construct(
        private TranslatorInterface $translator,
        private ShopDoor $door,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $request = $event->getRequest();

        $hidden = ($throwable instanceof NotFoundHttpException && ShopApiDoor::isShopApi($request))
            || ($throwable instanceof MethodNotAllowedHttpException && str_starts_with(rawurldecode($request->getPathInfo()), self::PUBLIC_PATH) && !$this->door->admits(false));

        if (!$hidden) {
            return;
        }

        $event->setResponse(new ProblemResponse($this->translator->trans('problem.not_found.title'), Response::HTTP_NOT_FOUND, $this->translator->trans('problem.not_found.detail'), 'problem.not_found'));
    }
}
