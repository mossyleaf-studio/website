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

    private const array UNSET = ['MAILER_DSN', 'STUDIO_EMAIL', 'MAILER_FROM'];

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
        self::assertSame('contact', $data['page']);
        self::assertSame('Contact', $data['title']);
        self::assertSame('/beta/api/shop/fr/contact', $data['endpoint']);
        self::assertSame(['Instagram', 'Nouveaux dessins', 'https://www.instagram.com/mossyleaf.studio/', 'blossom'], array_slice(array_values(Json::array($data, 'links', 2)), 1));
        self::assertContains(['key' => 'contact', 'url' => '/beta/fr/contact', 'current' => true], Json::array($props, 'layout', 'navigation'));
        self::assertSame('Contact · mossyleaf.studio', $crawler->filter('title')->text());

        $shop = $crawler->filter('#shop');
        self::assertSame('Contact', $shop->filter('main h1')->text());
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

    public function testTheFormPointsAtTheRouteOfThePageLanguageAndIsOfferedOnlyWithAStudioAddressAndASender(): void
    {
        $crawler = self::signedInClient()->request('GET', '/beta/en/contact');

        self::assertSame('/beta/api/shop/en/contact', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'data', 'endpoint'));
        self::assertCount(1, $crawler->filter('#shop main [data-noscript]'));

        foreach (['STUDIO_EMAIL', 'MAILER_FROM'] as $name) {
            self::ensureKernelShutdown();
            $_ENV[$name] = '';
            $crawler = self::signedInClient()->request('GET', '/beta/en/contact');
            $_ENV[$name] = $this->saved[$name];

            self::assertResponseIsSuccessful($name);
            $data = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data');
            self::assertNull($data['endpoint'], $name);
            self::assertCount(0, $crawler->filter('#shop main [data-noscript]'), $name);
            self::assertNotEmpty(Json::array($data, 'links'), $name);
            self::assertCount(\count(Json::array($data, 'links')), $crawler->filter('#shop main [data-links] li'), $name);
        }
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
