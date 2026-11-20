<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ShopContactPageTest extends WebTestCase
{
    use SignsInClient;

    private const array UNSET = ['MAILER_DSN', 'STUDIO_EMAIL'];

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::UNSET as $name) {
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
        parent::tearDown();
    }

    public function testTheContactPageStaysClosedToVisitors(): void
    {
        $client = self::createClient();

        foreach (['/fr/contact', '/en/contact'] as $url) {
            $client->request('GET', $url);

            self::assertResponseRedirects('/', 302, $url);
            self::assertResponseNotHasHeader('Set-Cookie', $url);
        }
    }

    public function testThePreviewOfTheContactPageNeedsSignIn(): void
    {
        self::createClient()->request('GET', '/beta/fr/contact');

        self::assertResponseRedirects('/login');
    }

    public function testEditorsPreviewTheFormAndTheLinksOfTheStudioInFrench(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/admin/links', ['title' => 'Instagram', 'description' => 'New drawings', 'url' => 'https://www.instagram.com/mossyleaf.studio/', 'tape' => 'blossom', 'titleFr' => 'Instagram', 'descriptionFr' => 'Nouveaux dessins']);

        $crawler = $client->request('GET', '/beta/fr/contact');

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('contact', $crawler->filter('#shop')->attr('data-page'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        $props = Json::decode($crawler->filter('#site-content')->text());
        $data = Json::array($props, 'data');
        self::assertSame(['page', 'title', 'endpoint', 'links'], array_keys($data));
        self::assertSame('contact', $data['page']);
        self::assertSame('Contact', $data['title']);
        self::assertSame('/beta/api/shop/fr/contact', $data['endpoint']);
        self::assertSame(['id', 'title', 'description', 'url', 'tape'], array_keys(Json::array($data, 'links', 0)));
        self::assertSame(['Instagram', 'Nouveaux dessins', 'https://www.instagram.com/mossyleaf.studio/', 'blossom'], array_slice(array_values(Json::array($data, 'links', 2)), 1));
        self::assertSame(['key' => 'contact', 'url' => '/beta/fr/contact', 'current' => true], Json::array($props, 'layout', 'navigation', 4));
        self::assertSame('Contact · mossyleaf.studio', $crawler->filter('title')->text());

        $shop = $crawler->filter('#shop');
        self::assertSame('Contact', $shop->filter('main h1')->text());
        self::assertSame('Une question, une commande ou simplement un bonjour : le studio lit chaque message.', $shop->filter('main [data-intro]')->text());
        self::assertSame('Le formulaire a besoin de JavaScript. Vous pouvez joindre le studio par les liens de cette page.', $shop->filter('main [data-noscript]')->text());
        self::assertSame('Retrouver le studio ailleurs', $shop->filter('main [data-links]')->attr('aria-label'));
        self::assertSame(
            array_map(static fn (mixed $link): array => [Json::string($link, 'title'), Json::string($link, 'url')], Json::array($data, 'links')),
            $shop->filter('main [data-links] a')->each(static fn (Crawler $link): array => [$link->filter('h2')->text(), (string) $link->attr('href')]),
        );
        self::assertSame('Nouveaux dessins', $shop->filter('main [data-links] li')->last()->filter('p')->text());
        foreach ($shop->filter('.shop-prerender > :not([data-draft]) a')->each(static fn (Crawler $link): string => (string) $link->attr('href')) as $href) {
            if (str_starts_with($href, '/')) {
                self::assertStringStartsWith('/beta/', $href);
            }
        }
    }

    public function testTheEnglishPageSendsToTheEnglishRoute(): void
    {
        $crawler = self::signedInClient()->request('GET', '/beta/en/contact');

        self::assertSame('/beta/api/shop/en/contact', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'data', 'endpoint'));
    }

    public function testWithoutAStudioAddressThePageOffersNoForm(): void
    {
        $_ENV['STUDIO_EMAIL'] = '';
        $crawler = self::signedInClient()->request('GET', '/beta/en/contact');

        self::assertResponseIsSuccessful();
        $data = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data');
        self::assertNull($data['endpoint']);
        self::assertSame('Questions, commissions or simply a hello: the studio reads every message.', $crawler->filter('#shop main [data-intro]')->text());
        self::assertCount(0, $crawler->filter('#shop main [data-noscript]'));
        self::assertNotEmpty(Json::array($data, 'links'));
        self::assertCount(\count(Json::array($data, 'links')), $crawler->filter('#shop main [data-links] li'));
    }

    public function testTheApplicationBootsWithTheMailSettingsUnset(): void
    {
        foreach (self::UNSET as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }
        $client = self::signedInClient();

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSame('note', $client->getCrawler()->filter('#site')->attr('data-page'));

        $crawler = $client->request('GET', '/beta/fr/contact');
        self::assertResponseIsSuccessful();
        self::assertNull(Json::at(Json::decode($crawler->filter('#site-content')->text()), 'data', 'endpoint'));

        $client->jsonRequest('POST', '/beta/api/shop/fr/contact', ['name' => 'Lou', 'email' => 'lou@example.test', 'message' => 'Hello', 'website' => '']);
        self::assertResponseStatusCodeSame(404);
        self::assertEmailCount(0);
    }
}
