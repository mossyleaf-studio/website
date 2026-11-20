<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use Symfony\Component\HttpFoundation\Request;

final readonly class ShopDoor
{
    public const array EXEMPT = ['shop_order', 'api_shop_orders_status'];

    public function admits(bool $preview): bool
    {
        return $preview;
    }

    public static function exempts(Request $request): bool
    {
        return \in_array(ShopUrls::route($request), self::EXEMPT, true);
    }
}
