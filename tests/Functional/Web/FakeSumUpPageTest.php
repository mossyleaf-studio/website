<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Domain\Sales\OrderStatus;
use App\Kernel;
use App\Tests\Functional\Sales\ShopsOnline;
use App\Tests\Support\FakeSumUp;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;

final class FakeSumUpPageTest extends WebTestCase
{
    use ShopsOnline;

    protected function tearDown(): void
    {
        self::forgetCheckouts();
        parent::tearDown();
    }

    public function testTheHostedPageShowsTheCheckoutAndPaysThroughTheWebhook(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        $client->getCookieJar()->clear();

        $crawler = $client->request('GET', $placed['paymentUrl']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($placed['reference'], $crawler->filter('[data-description]')->text());
        self::assertStringContainsString('12.50', $crawler->filter('[data-amount]')->text());
        self::assertSame('http://localhost/beta/fr/order/'.$placed['token'], $crawler->selectLink('Back to the shop')->attr('href'));

        $client->submit($crawler->selectButton('Pay')->form());

        self::assertResponseRedirects('http://localhost/beta/fr/order/'.$placed['token']);
        self::assertSame(OrderStatus::Paid, self::order($placed['token'], fresh: true)->status());
        self::assertSame(FakeSumUp::PAID, self::sumUp()->checkout($placed['paymentId'])['status'] ?? null);
    }

    public function testDecliningFailsTheOrderThroughTheWebhook(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);

        $client->submit($client->request('GET', $placed['paymentUrl'])->selectButton('Decline')->form());

        self::assertResponseRedirects('http://localhost/beta/fr/order/'.$placed['token']);
        self::assertSame(OrderStatus::Failed, self::order($placed['token'], fresh: true)->status());
    }

    public function testAFlagSkipsTheWebhookSoTheStatusPageSettlesTheOrderAlone(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);

        $client->submit($client->request('GET', $placed['paymentUrl'].'?webhook=0')->selectButton('Pay')->form());

        self::assertSame(FakeSumUp::PAID, self::sumUp()->checkout($placed['paymentId'])['status'] ?? null);
        self::assertSame(OrderStatus::Pending, self::order($placed['token'], fresh: true)->status());

        $client->request('GET', '/beta/fr/order/'.$placed['token']);

        self::assertSame(OrderStatus::Paid, self::order($placed['token'], fresh: true)->status());
    }

    public function testAnUnknownCheckoutIsNotFound(): void
    {
        self::createClient()->request('GET', '/_fake/sumup/nope');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheFakeExistsInTheTestEnvironmentOnly(): void
    {
        self::assertNotNull(self::getContainer()->get(RouterInterface::class)->getRouteCollection()->get('fake_sumup_page'));
        $cache = \dirname(__DIR__, 3).'/var/cache/prod-fake-sumup-check';
        $_SERVER['APP_CACHE_DIR'] = $cache;
        $kernel = new Kernel('prod', false);

        try {
            $kernel->boot();
            $container = $kernel->getContainer();
            $router = $container->get('router');
            $paths = array_map(static fn ($route): string => $route->getPath(), $router->getRouteCollection()->all());

            self::assertNotContains('/_fake/sumup/{id}', $paths);
            self::assertContains('/webhooks/sumup', $paths);
        } finally {
            $kernel->shutdown();
            unset($_SERVER['APP_CACHE_DIR']);
            new Filesystem()->remove($cache);
        }
    }
}
