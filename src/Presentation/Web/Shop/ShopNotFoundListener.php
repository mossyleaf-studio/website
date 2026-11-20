<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Content\ContentQueries;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final readonly class ShopNotFoundListener
{
    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
        private ContentQueries $queries,
        private ShopPage $page,
        private SiteLabels $labels,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->getThrowable() instanceof NotFoundHttpException || !ShopUrls::isShop($request)) {
            return;
        }

        $preview = $request->attributes->getBoolean('preview');
        if (!$this->door->admits($preview)) {
            $event->setResponse(new RedirectResponse($this->urls->closed()));

            return;
        }

        $title = $this->labels->label(ShopUrls::locale($request)->value, 'notFound.title');

        $event->setResponse($this->page->render($request, $preview, $this->queries->shop($preview), ['page' => 'not-found'], $title, Response::HTTP_NOT_FOUND));
    }
}
