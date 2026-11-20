<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Functional\Content\OpensTheShop;
use App\Tests\Functional\Sales\ShopsOnline;
use App\Tests\Support\Json;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ShopDoorTest extends WebTestCase
{
    use OpensTheShop;
    use ShopsOnline;

    private const array PAGES = ['/fr/', '/en/', '/fr/shop', '/en/shop?category=prints', '/fr/shop/prints-fox', '/en/shop/no-such-product', '/en/cart', '/fr/checkout', '/en/conventions', '/fr/about', '/en/contact', '/fr/legal/terms', '/fr/no-such-page'];
    private const array TRICKS = ['/fr/?preview=1', '/fr/shop?preview=true', '/fr/%73hop', '/%66r/about'];

    protected function tearDown(): void
    {
        self::forgetCheckouts();
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    /** @return iterable<string, array{?string}> */
    public static function closedStates(): iterable
    {
        yield 'nothing ever published' => [null];
        yield 'the note published' => ['note'];
        yield 'a full publication older than the shop texts' => ['legacy'];
    }

    #[DataProvider('closedStates')]
    public function testAClosedShopKeepsEveryPublicAddressShut(?string $state): void
    {
        $client = self::stockedClient();
        self::readyTheShop($client);
        $placed = self::placeThroughTheShop($client);
        self::enter($client, $state);
        $client->getCookieJar()->clear();

        foreach ([...self::PAGES, ...self::TRICKS] as $url) {
            $client->request('GET', $url, server: ['HTTP_PREVIEW' => '1']);

            self::assertResponseRedirects('/', 302, $url);
            self::assertResponseNotHasHeader('Set-Cookie', $url);
        }

        foreach (self::calls() as $url => $body) {
            foreach ([$url, $url.'?preview=1'] as $address) {
                $client->jsonRequest('POST', $address, $body, ['HTTP_PREVIEW' => '1']);

                self::assertResponseStatusCodeSame(404, $address);
                self::assertSame('problem.not_found', Json::string(self::answer($client), 'code'), $address);
                self::assertResponseNotHasHeader('Set-Cookie', $address);
            }
        }

        self::assertAnExistingOrderStillAnswers($client, $placed['token']);

        $client->request('GET', '/fr/order/'.str_repeat('A', 43));
        self::assertResponseRedirects('/', 302);

        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSame('note', $crawler->filter('#site')->attr('data-page'));

        $client->request('GET', '/%62eta/fr/');
        self::assertResponseRedirects('/login');
    }

    public function testAnOpenShopAnswersEveryPublicAddressWithoutTheEditorialBits(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::openTheShop($client);
        $client->getCookieJar()->clear();

        foreach ([...self::PAGES, ...self::TRICKS] as $url) {
            $crawler = $client->request('GET', $url, server: ['HTTP_PREVIEW' => '1']);
            $found = !str_contains($url, 'no-such');

            self::assertResponseStatusCodeSame($found ? 200 : 404, $url);
            self::assertResponseNotHasHeader('Set-Cookie', $url);
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('public'), $url);
            self::assertNull($crawler->filter('#shop')->attr('data-preview'), $url);
            self::assertCount(0, $crawler->filter('[data-draft]'), $url);
            $props = $crawler->filter('#site-content')->text();
            self::assertStringNotContainsString('preview', $props, $url);
            self::assertStringNotContainsString('hidden', $props, $url);
            self::assertStringNotContainsString('/beta/', $props, $url);
        }

        $client->jsonRequest('POST', '/api/shop/en/quote', ['lines' => [['sku' => 'STI-MOTH', 'quantity' => 1]], 'country' => 'FR']);
        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Set-Cookie');
        $client->jsonRequest('POST', '/api/shop/fr/orders', []);
        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('POST', '/api/shop/en/contact', []);
        self::assertResponseStatusCodeSame(422);

        self::assertAnExistingOrderStillAnswers($client, $placed['token']);

        $crawler = $client->request('GET', '/fr/order/'.str_repeat('A', 43));
        self::assertResponseStatusCodeSame(404);
        self::assertSame('not-found', $crawler->filter('#shop')->attr('data-page'));

        $client->request('GET', '/beta/fr/');
        self::assertResponseRedirects('/login');
        $client->jsonRequest('POST', '/beta/api/shop/en/quote', ['lines' => [], 'country' => 'FR']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testShowingTheNoteAgainClosesTheShop(): void
    {
        $client = self::stockedClient();
        self::openTheShop($client);
        self::publish($client, 'note');
        $client->getCookieJar()->clear();

        foreach (self::PAGES as $url) {
            $client->request('GET', $url);

            self::assertResponseRedirects('/', 302, $url);
        }
        $client->jsonRequest('POST', '/api/shop/en/quote', ['lines' => [], 'country' => 'FR']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('note', $client->request('GET', '/')->filter('#site')->attr('data-page'));
    }

    /** @return array<string, array<string, mixed>> */
    private static function calls(): array
    {
        return [
            '/api/shop/en/quote' => ['lines' => [], 'country' => 'FR'],
            '/api/shop/fr/orders' => self::form(),
            '/api/shop/en/contact' => ['name' => 'Lou', 'email' => 'lou@example.test', 'message' => 'Hello there, a question about the fox.'],
        ];
    }

    private static function enter(KernelBrowser $client, ?string $state): void
    {
        if ('note' === $state) {
            self::publish($client, 'note');
        }
        if ('legacy' === $state) {
            self::publish($client, 'full');
            self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement('UPDATE published_site SET shop_content = NULL');
        }
    }

    private static function assertAnExistingOrderStillAnswers(KernelBrowser $client, string $token): void
    {
        $crawler = $client->request('GET', '/fr/order/'.$token);
        self::assertResponseIsSuccessful();
        self::assertSame('order', $crawler->filter('#shop')->attr('data-page'));
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));

        $client->jsonRequest('GET', '/api/shop/fr/orders/'.$token);
        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Set-Cookie');
    }
}
