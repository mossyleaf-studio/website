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

    public function testEditorsPreviewTheCartInFrenchWithPreviewAddresses(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/beta/fr/cart');

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('cart', $crawler->filter('#shop')->attr('data-page'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertCount(0, $crawler->filter('link[rel="canonical"]'));
        self::assertCount(0, $crawler->filter('link[rel="alternate"]'));
        $props = Json::decode($crawler->filter('#site-content')->text());
        self::assertSame('/beta/fr/cart', Json::string($props, 'layout', 'cartUrl'));
        $data = Json::array($props, 'data');
        self::assertSame(['cart', '/beta/api/shop/fr/quote', 'FR', '/beta/fr/shop', '/beta/fr/checkout'], [$data['page'], $data['quoteUrl'], $data['defaultCountry'], $data['shopUrl'], $data['checkoutUrl']]);
        self::assertSame('/beta/fr/shop', $crawler->filter('#shop main a[data-shop]')->attr('href'));
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
