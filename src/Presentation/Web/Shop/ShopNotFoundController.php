<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/{_locale}/{path}', name: 'shop_not_found', requirements: ['_locale' => 'en|fr', 'path' => '.+'], methods: ['GET'], stateless: true, priority: -100)]
final readonly class ShopNotFoundController
{
    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
    ) {
    }

    public function __invoke(bool $preview = false): RedirectResponse
    {
        if (!$this->door->admits($preview)) {
            return new RedirectResponse($this->urls->closed());
        }

        throw new NotFoundHttpException();
    }
}
