<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use App\Presentation\Web\Shop\ShopDoor;
use App\Presentation\Web\Shop\ShopUrls;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final readonly class ShopApiDoor
{
    public const string ROUTE = 'api_shop_';

    public function __construct(private ShopDoor $door)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (self::isShopApi($request) && !ShopDoor::exempts($request) && !$this->door->admits($request->attributes->getBoolean('preview'))) {
            throw new NotFoundHttpException();
        }
    }

    public static function isShopApi(Request $request): bool
    {
        return str_starts_with(ShopUrls::route($request), self::ROUTE);
    }
}
