<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Infrastructure\Security\SecurityUser;
use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Functional\Content\OpensTheShop;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

final class OpenShopPagesTest extends WebTestCase
{
    use OpensTheShop;
    use PushesCatalogue;
    use SignsInClient;

    private const array INDEXABLE = ['/en/', '/fr/', '/en/category/prints', '/fr/category/prints', '/en/shop/prints-fox', '/fr/conventions', '/en/about', '/fr/contact', '/en/legal/terms'];

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testVisitorsReadTheFrozenTextsNotTheDraft(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Frogs in the ferns', 'metaDescription' => 'Drawings', 'introFr' => 'Grenouilles dans les fougères']);
        $client->jsonRequest('PUT', '/api/admin/texts/about', ['title' => 'About', 'text' => 'Published about.', 'titleFr' => 'À propos', 'textFr' => 'Publié.']);
        self::openTheShop($client);
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Snails on the moss', 'metaDescription' => 'Drawings', 'introFr' => 'Escargots sur la mousse']);
        $client->jsonRequest('PUT', '/api/admin/texts/about', ['title' => 'About', 'text' => 'Draft about.', 'titleFr' => 'À propos', 'textFr' => 'Brouillon.']);
        $client->jsonRequest('PUT', '/api/admin/legal-pages/terms', ['text' => '## Draft terms', 'textFr' => '']);
        $visitor = self::visitor($client);

        self::assertSame('Frogs in the ferns', Json::string(self::props($visitor, '/en/'), 'data', 'intro'));
        self::assertSame('Grenouilles dans les fougères', Json::string(self::props($visitor, '/fr/'), 'data', 'intro'));
        self::assertSame('Published about.', Json::string(self::props($visitor, '/en/about'), 'data', 'paragraphs', 0));
        self::assertStringContainsString('The studio sells drawings.', Json::string(self::props($visitor, '/en/legal/terms'), 'data', 'text'));
        self::assertStringNotContainsString('Snails', (string) $visitor->getResponse()->getContent());

        $client->loginUser(self::editor());
        self::assertSame('Snails on the moss', Json::string(self::props($client, '/beta/en/'), 'data', 'intro'));
    }

    public function testAHiddenOrRetiredProductIsNowhereForVisitors(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);
        self::openTheShop($client);
        $visitor = self::visitor($client);

        foreach (['/en/shop/stickers-moth', '/fr/shop/cards-bee', '/en/shop/prints-hare', '/en/category/stickers', '/fr/category/cards'] as $url) {
            $crawler = $visitor->request('GET', $url);

            self::assertResponseStatusCodeSame(404, $url);
            self::assertSame('not-found', $crawler->filter('#shop')->attr('data-page'), $url);
            self::assertResponseNotHasHeader('Set-Cookie', $url);
        }

        $listed = ['/en/shop/prints-fox', '/en/shop/prints-owl'];
        self::assertSame($listed, self::cardUrls(Json::array(self::props($visitor, '/en/category/prints'), 'data', 'products')));
        self::assertSame(['prints'], array_map(static fn (mixed $child): string => Json::string($child, 'key'), Json::array(self::props($visitor, '/en/'), 'layout', 'navigation', 1, 'children')));
        self::assertSame($listed, self::cardUrls(Json::array(self::props($visitor, '/en/'), 'data', 'products')));
        self::assertSame(['/en/shop/prints-owl'], self::cardUrls(Json::array(self::props($visitor, '/en/shop/prints-fox'), 'data', 'related')));

        $visitor->jsonRequest('POST', '/api/shop/en/quote', ['lines' => [['sku' => 'STI-MOTH', 'quantity' => 1], ['sku' => 'PRI-FOX-A4', 'quantity' => 1]], 'country' => 'FR']);
        self::assertResponseIsSuccessful();
        $quote = Json::decode((string) $visitor->getResponse()->getContent());
        self::assertSame(['PRI-FOX-A4'], array_map(static fn (mixed $line): string => Json::string($line, 'sku'), Json::array($quote, 'lines')));
        self::assertSame('STI-MOTH', Json::string($quote, 'issues', 0, 'sku'));
    }

    public function testEachIndexablePageCarriesAnEtagOfWhatItShows(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);
        self::openTheShop($client);
        $visitor = self::visitor($client);

        $etags = [];
        foreach (self::INDEXABLE as $url) {
            $visitor->request('GET', $url);
            $response = $visitor->getResponse();

            self::assertResponseIsSuccessful($url);
            self::assertResponseNotHasHeader('Set-Cookie', $url);
            self::assertTrue($response->headers->hasCacheControlDirective('public'), $url);
            self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'), $url);
            self::assertSame('0', $response->headers->getCacheControlDirective('max-age'), $url);
            $etag = (string) $response->getEtag();
            self::assertSame('"'.hash('xxh128', (string) $response->getContent()).'"', $etag, $url);
            $etags[$url] = $etag;

            $visitor->request('GET', $url, server: ['HTTP_IF_NONE_MATCH' => $etag]);
            self::assertResponseStatusCodeSame(304, $url);
            self::assertSame('', (string) $visitor->getResponse()->getContent(), $url);
        }
        self::assertSame(array_values($etags), array_values(array_unique($etags)));

        $visitor->request('GET', '/en/category/prints', server: ['HTTP_IF_NONE_MATCH' => $etags['/en/']]);
        self::assertResponseStatusCodeSame(200);

        self::push([self::product(1, 'Prints', 'Fox', 1800, ['A4' => 12, 'A5' => 0]), ...\array_slice(self::demo(), 1, 3)]);
        $visitor->request('GET', '/en/category/prints', server: ['HTTP_IF_NONE_MATCH' => $etags['/en/category/prints']]);
        self::assertResponseStatusCodeSame(200);
        self::assertNotSame($etags['/en/category/prints'], (string) $visitor->getResponse()->getEtag());
    }

    public function testTheCartTheCheckoutTheNotFoundPageAndThePreviewCarryNoEtag(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);
        self::openTheShop($client);

        foreach (['/beta/en/', '/beta/fr/category/prints', '/beta/en/shop/prints-fox', '/beta/en/about'] as $url) {
            $client->request('GET', $url);

            self::assertResponseIsSuccessful($url);
            self::assertNull($client->getResponse()->getEtag(), $url);
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'), $url);
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'), $url);
            self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        }

        $visitor = self::visitor($client);
        foreach (['/en/cart' => 200, '/fr/checkout' => 200, '/en/no-such-page' => 404, '/en/shop/no-such-product' => 404] as $url => $status) {
            $crawler = $visitor->request('GET', $url);

            self::assertResponseStatusCodeSame($status, $url);
            self::assertNull($visitor->getResponse()->getEtag(), $url);
            self::assertStringStartsWith('noindex', (string) $crawler->filter('meta[name="robots"]')->attr('content'), $url);
        }
    }

    /** @return array<mixed> */
    private static function props(KernelBrowser $client, string $url): array
    {
        $crawler = $client->request('GET', $url);
        self::assertResponseIsSuccessful($url);

        return Json::decode($crawler->filter('#site-content')->text());
    }

    /**
     * @param array<mixed> $cards
     *
     * @return list<string>
     */
    private static function cardUrls(array $cards): array
    {
        return array_values(array_map(static fn (mixed $card): string => Json::string($card, 'url'), $cards));
    }

    private static function editor(): SecurityUser
    {
        return SecurityUser::fromUser(self::createUser());
    }

    private static function visitor(KernelBrowser $client): KernelBrowser
    {
        $client->getCookieJar()->clear();

        return $client;
    }
}
