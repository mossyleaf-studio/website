<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Application\Catalogue\ReplaceCatalogue\PushedVariant;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogue;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogueHandler;
use App\Kernel;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Ulid;

final class FakeProductsTest extends WebTestCase
{
    use SignsInClient;

    public function testAddedProductsComeAfterTheExistingOnesAndGoAwayAgain(): void
    {
        $client = self::signedInClient();
        self::getContainer()->get(ReplaceCatalogueHandler::class)(new ReplaceCatalogue([
            new PushedProduct(new Ulid(), 'PRT-FOX', 'Fox', 'Prints', 'Prints Fox', 1500, [new PushedVariant('A4', 'PRT-FOX-A4', 12)]),
        ]));
        $before = self::references($client);

        $client->request('POST', '/_fake/products/25');
        self::assertResponseStatusCodeSame(204);
        $after = self::references($client);

        self::assertCount(26, $after);
        self::assertSame($before, \array_slice($after, 0, 1));
        self::assertSame('FAKE-PAGE-001', $after[1]);
        self::assertSame('FAKE-PAGE-025', $after[25]);

        $client->request('DELETE', '/_fake/products');
        self::assertResponseStatusCodeSame(204);
        self::assertSame($before, self::references($client));

        $client->request('DELETE', '/_fake/products');
        self::assertResponseStatusCodeSame(204);
        self::assertSame($before, self::references($client));
    }

    public function testTheCountIsBounded(): void
    {
        self::createClient()->request('POST', '/_fake/products/201');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheRoutesExistInTheTestEnvironmentOnly(): void
    {
        $routes = self::getContainer()->get(RouterInterface::class)->getRouteCollection();
        self::assertNotNull($routes->get('fake_products_add'));
        self::assertNotNull($routes->get('fake_products_remove'));
        $cache = \dirname(__DIR__, 3).'/var/cache/prod-fake-products-check';
        $_SERVER['APP_CACHE_DIR'] = $cache;
        $kernel = new Kernel('prod', false);

        try {
            $kernel->boot();
            $paths = array_map(static fn ($route): string => $route->getPath(), $kernel->getContainer()->get('router')->getRouteCollection()->all());

            self::assertNotContains('/_fake/products/{count}', $paths);
            self::assertNotContains('/_fake/products', $paths);
        } finally {
            $kernel->shutdown();
            unset($_SERVER['APP_CACHE_DIR']);
            new Filesystem()->remove($cache);
        }
    }

    /**
     * @return list<string>
     */
    private static function references(KernelBrowser $client): array
    {
        $client->jsonRequest('GET', '/api/admin/products');

        return array_values(array_map(static fn (mixed $product): string => Json::string($product, 'reference'), Json::decode((string) $client->getResponse()->getContent())));
    }
}
