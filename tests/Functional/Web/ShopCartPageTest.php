<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ShopCartPageTest extends WebTestCase
{
    use SignsInClient;

    public function testTheCartStaysClosedToVisitorsWithoutACookie(): void
    {
        $client = self::createClient();

        foreach (['/fr/cart', '/en/cart'] as $url) {
            $client->request('GET', $url);

            self::assertResponseRedirects('/', 302, $url);
            self::assertResponseNotHasHeader('Set-Cookie', $url);
        }
    }

    public function testThePreviewOfTheCartNeedsSignIn(): void
    {
        self::createClient()->request('GET', '/beta/fr/cart');

        self::assertResponseRedirects('/login');
    }

    public function testEditorsPreviewTheCartInFrenchWithPreviewAddresses(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/beta/fr/cart');

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('cart', $crawler->filter('#shop')->attr('data-page'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('Panier · mossyleaf.studio', $crawler->filter('title')->text());
        $props = Json::decode($crawler->filter('#site-content')->text());
        self::assertSame('/beta/fr/cart', Json::string($props, 'layout', 'cartUrl'));
        self::assertSame(
            ['page' => 'cart', 'title' => 'Panier', 'quoteUrl' => '/beta/api/shop/fr/quote', 'countries' => [], 'defaultCountry' => 'FR', 'shopUrl' => '/beta/fr/shop', 'checkoutUrl' => null],
            Json::array($props, 'data'),
        );

        $main = $crawler->filter('#shop main');
        self::assertSame('Panier', $main->filter('h1')->text());
        self::assertSame('/beta/fr/shop', $main->filter('a[data-shop]')->attr('href'));
        self::assertSame('Retour à la boutique', $main->filter('a[data-shop]')->text());
        self::assertSame('Le panier a besoin de JavaScript pour compter vos articles et calculer la livraison.', $main->filter('[data-noscript]')->text());
        foreach ($crawler->filter('.shop-prerender > :not([data-draft]) a')->each(static fn (Crawler $link): string => (string) $link->attr('href')) as $href) {
            if (str_starts_with($href, '/')) {
                self::assertStringStartsWith('/beta/', $href);
            }
        }
    }

    public function testOnlyTheCountriesOfTheZonesThatAreOnAreOfferedSortedByName(): void
    {
        $client = self::signedInClient();
        self::zone($client, 'france', true);
        self::zone($client, 'eu', true);

        $countries = self::countries($client, 'fr');

        self::assertCount(28, $countries);
        self::assertSame(['code' => 'DE', 'name' => 'Allemagne'], $countries[0]);
        self::assertContains(['code' => 'FR', 'name' => 'France'], $countries);
        self::assertContains(['code' => 'MC', 'name' => 'Monaco'], $countries);
        self::assertNotContains('CH', array_column($countries, 'code'));
        $names = array_map(static fn (mixed $country): string => Json::string($country, 'name'), $countries);
        $sorted = $names;
        new \Collator('fr')->sort($sorted);
        self::assertSame($sorted, $names);

        self::zone($client, 'eu', false);
        self::zone($client, 'world', true);
        $world = self::countries($client, 'en');

        self::assertContains(['code' => 'US', 'name' => 'United States'], $world);
        self::assertContains(['code' => 'FR', 'name' => 'France'], $world);
        self::assertNotContains('DE', array_column($world, 'code'));
    }

    public function testTheCartHasNoCanonicalNorAlternates(): void
    {
        $client = self::signedInClient();
        $crawler = $client->request('GET', '/beta/en/cart');

        self::assertSame('/beta/api/shop/en/quote', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'data', 'quoteUrl'));
        self::assertCount(0, $crawler->filter('link[rel="canonical"]'));
        self::assertCount(0, $crawler->filter('link[rel="alternate"]'));
    }

    private static function zone(KernelBrowser $client, string $code, bool $enabled): void
    {
        $client->jsonRequest('PUT', "/api/admin/shipping-zones/{$code}", ['price' => 500, 'freeFrom' => null, 'enabled' => $enabled]);
        self::assertResponseIsSuccessful();
    }

    /** @return list<mixed> */
    private static function countries(KernelBrowser $client, string $locale): array
    {
        $crawler = $client->request('GET', "/beta/{$locale}/cart");

        return array_values(Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data', 'countries'));
    }
}
