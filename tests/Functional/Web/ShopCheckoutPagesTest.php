<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Functional\Sales\ShopsOnline;
use App\Tests\Support\FakeSumUp;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ShopCheckoutPagesTest extends WebTestCase
{
    use ShopsOnline;

    private const array SETTINGS = ['SUMUP_API_KEY', 'SUMUP_MERCHANT_CODE'];

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::SETTINGS as $name) {
            $this->saved[$name] = $_ENV[$name] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            if (\is_string($value)) {
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
        }
        self::forgetCheckouts();
        parent::tearDown();
    }

    public function testTheCheckoutStaysClosedToVisitorsWithoutACookie(): void
    {
        $client = self::createClient();

        foreach (['/fr/checkout', '/en/checkout'] as $url) {
            $client->request('GET', $url);

            self::assertResponseRedirects('/', 302, $url);
            self::assertResponseNotHasHeader('Set-Cookie', $url);
        }
    }

    public function testThePreviewOfTheCheckoutNeedsSignIn(): void
    {
        self::createClient()->request('GET', '/beta/fr/checkout');

        self::assertResponseRedirects('/login');
    }

    public function testEditorsPreviewTheCheckoutInFrenchWithPreviewAddresses(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/beta/fr/checkout');

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('checkout', $crawler->filter('#shop')->attr('data-page'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertCount(0, $crawler->filter('link[rel="canonical"]'));
        self::assertCount(0, $crawler->filter('link[rel="alternate"]'));
        self::assertSame('Commande · mossyleaf.studio', $crawler->filter('title')->text());
        self::assertSame(
            ['page' => 'checkout', 'title' => 'Commande', 'quoteUrl' => '/beta/api/shop/fr/quote', 'ordersUrl' => '/beta/api/shop/fr/orders', 'countries' => [], 'defaultCountry' => 'FR', 'termsUrl' => null, 'cartUrl' => '/beta/fr/cart'],
            Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data'),
        );
        $main = $crawler->filter('#shop main');
        self::assertSame('Commande', $main->filter('h1')->text());
        self::assertSame('/beta/fr/cart', $main->filter('a[data-cart-back]')->attr('href'));
        foreach ($crawler->filter('.shop-prerender > :not([data-draft]) a')->each(static fn (Crawler $link): string => (string) $link->attr('href')) as $href) {
            if (str_starts_with($href, '/')) {
                self::assertStringStartsWith('/beta/', $href);
            }
        }
    }

    public function testTheCheckoutOffersTheCountriesAndLinksTheTermsOnceTheyHaveText(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/shipping-zones/france', ['price' => 650, 'freeFrom' => null, 'enabled' => true]);
        $client->jsonRequest('PUT', '/api/admin/legal-pages/terms', ['text' => '## Terms', 'textFr' => '']);
        self::assertResponseIsSuccessful();

        $data = Json::array(Json::decode($client->request('GET', '/beta/fr/checkout')->filter('#site-content')->text()), 'data');

        self::assertSame('/beta/fr/legal/terms', $data['termsUrl']);
        self::assertSame([['code' => 'FR', 'name' => 'France'], ['code' => 'MC', 'name' => 'Monaco']], $data['countries']);
    }

    public function testWithoutSumUpThereIsNoCheckout(): void
    {
        foreach (self::SETTINGS as $name) {
            $_ENV[$name] = '';
            $client = self::signedInClient();

            $crawler = $client->request('GET', '/beta/fr/checkout');

            self::assertResponseStatusCodeSame(404, $name);
            self::assertSame('not-found', $crawler->filter('#shop')->attr('data-page'));

            $cart = $client->request('GET', '/beta/fr/cart');

            self::assertNull(Json::at(Json::decode($cart->filter('#site-content')->text()), 'data', 'checkoutUrl'), $name);
            $_ENV[$name] = $this->saved[$name];
            self::ensureKernelShutdown();
        }
    }

    public function testTheApplicationBootsWithTheSumUpSettingsUnset(): void
    {
        foreach (self::SETTINGS as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }
        $client = self::signedInClient();

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $cart = $client->request('GET', '/beta/fr/cart');
        self::assertResponseIsSuccessful();
        self::assertNull(Json::at(Json::decode($cart->filter('#site-content')->text()), 'data', 'checkoutUrl'));

        $client->request('POST', '/webhooks/sumup', server: ['CONTENT_TYPE' => 'application/json'], content: '{"event_type": "CHECKOUT_STATUS_CHANGED", "id": "chk-1"}');
        self::assertResponseStatusCodeSame(204);
    }

    public function testTheCartOffersTheCheckoutWhenSumUpIsReady(): void
    {
        $crawler = self::signedInClient()->request('GET', '/beta/en/cart');

        self::assertSame('/beta/en/checkout', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'data', 'checkoutUrl'));
    }

    public function testAnonymousVisitorsFollowTheirOrderPrivatelyWhileTheDoorIsShut(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        $client->getCookieJar()->clear();

        $crawler = $client->request('GET', '/fr/order/'.$placed['token']);

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Set-Cookie');
        $cacheControl = (string) $client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringNotContainsString('public', $cacheControl);
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertCount(0, $crawler->filter('link[rel="canonical"]'));
        self::assertSame('order', $crawler->filter('#shop')->attr('data-page'));
        $data = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data');
        self::assertSame(['page', 'title', 'order', 'statusUrl'], array_keys($data));
        self::assertSame(['order', 'Votre commande', '/api/shop/fr/orders/'.$placed['token']], [$data['page'], $data['title'], $data['statusUrl']]);

        $client->jsonRequest('GET', '/api/shop/fr/orders/'.$placed['token']);

        self::assertSame(self::answer($client), $data['order']);
        $main = $crawler->filter('#shop main');
        self::assertStringContainsString($placed['reference'], $main->filter('[data-reference]')->text());
        self::assertCount(1, $main->filter('[data-test-order]'));
        self::assertSame($placed['paymentUrl'], $main->filter('a[data-pay]')->attr('href'));
        self::assertStringNotContainsString(self::CUSTOMER[0], (string) $client->getResponse()->getContent());
    }

    public function testTheOrderPageSettlesAPaidCheckout(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);

        $crawler = $client->request('GET', '/beta/en/order/'.$placed['token']);

        self::assertResponseIsSuccessful();
        $data = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data');
        self::assertSame(['paid', null, '/beta/api/shop/en/orders/'.$placed['token']], [Json::at($data, 'order', 'status'), Json::at($data, 'order', 'paymentUrl'), $data['statusUrl']]);
        self::assertCount(0, $crawler->filter('#shop main a[data-pay]'));
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testAnUnknownOrderIsTheShopNotFoundPageEvenWhileTheDoorIsShut(): void
    {
        $client = self::createClient();

        foreach ([str_repeat('A', 43), 'short'] as $token) {
            $crawler = $client->request('GET', '/fr/order/'.$token);

            self::assertResponseStatusCodeSame(404, $token);
            self::assertSame('not-found', $crawler->filter('#shop')->attr('data-page'));
            self::assertResponseNotHasHeader('Set-Cookie');
        }
    }

    public function testThePreviewOfAnOrderNeedsSignIn(): void
    {
        self::createClient()->request('GET', '/beta/fr/order/'.str_repeat('A', 43));

        self::assertResponseRedirects('/login');
    }
}
