<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Presentation\Web\Shop\ShopUrls;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/beta/', name: 'beta', methods: ['GET'])]
final readonly class BetaController
{
    public function __construct(private ShopUrls $urls)
    {
    }

    public function __invoke(Request $request): Response
    {
        $response = new RedirectResponse($this->urls->to('shop_home', ShopUrls::preferredLocale($request), true));
        $response->setVary('Accept-Language');

        return $response;
    }
}
