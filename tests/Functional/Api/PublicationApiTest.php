<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Domain\Content\PublishedSiteRepository;
use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Functional\Content\OpensTheShop;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PublicationApiTest extends WebTestCase
{
    use OpensTheShop;
    use PushesCatalogue;
    use SignsInClient;

    private const array PREVIEW = ['shop' => ['en' => '/beta/en/', 'fr' => '/beta/fr/'], 'about' => ['en' => '/beta/en/about', 'fr' => '/beta/fr/about'], 'note' => '/beta/note/'];

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testNothingIsPublishedAtFirst(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/admin/publication');

        self::assertResponseIsSuccessful();
        self::assertSame(['page' => null, 'publishedAt' => null, 'publishedBy' => null, 'pendingChanges' => true, 'open' => false, 'preview' => self::PREVIEW], Json::decode((string) $client->getResponse()->getContent()));
    }

    public function testVisitorsOnlySeeWhatWasPublished(): void
    {
        $client = self::signedInClient();
        self::editIntro($client, 'Frogs in the ferns');

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
        self::assertResponseIsSuccessful();
        $publication = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['note', 'Louis', false, false, self::PREVIEW], [$publication['page'], $publication['publishedBy'], $publication['pendingChanges'], $publication['open'], $publication['preview']]);

        self::editIntro($client, 'Snails on the moss');
        $client->jsonRequest('GET', '/api/admin/publication');
        self::assertTrue(Json::decode((string) $client->getResponse()->getContent())['pendingChanges']);

        $crawler = $client->request('GET', '/');
        self::assertSame('note', $crawler->filter('#site')->attr('data-page'));
        self::assertSame('Frogs in the ferns', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'intro'));
        self::assertSame('Frogs in the ferns', $crawler->filter('#site .prerender__intro p')->text());

        $crawler = $client->request('GET', '/beta/note/');
        self::assertSame('Snails on the moss', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'intro'));
    }

    public function testPublishingTheFullSiteOpensTheShop(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);
        self::readyTheShop($client);

        $publication = self::withARealMailer(static function () use ($client): array {
            $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);

            return Json::decode((string) $client->getResponse()->getContent());
        });

        self::assertResponseIsSuccessful();
        self::assertSame(['page', 'publishedAt', 'publishedBy', 'pendingChanges', 'open', 'preview'], array_keys($publication));
        self::assertSame(['full', 'Louis', false, true, self::PREVIEW], [$publication['page'], $publication['publishedBy'], $publication['pendingChanges'], $publication['open'], $publication['preview']]);
        $client->jsonRequest('GET', '/api/admin/publication');
        self::assertSame($publication, Json::decode((string) $client->getResponse()->getContent()));
    }

    public function testTheFullSiteIsRefusedWhileABlockerStandsAndNothingChanges(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);
        $client->jsonRequest('PUT', '/api/admin/legal-pages/terms', ['text' => '## Terms', 'textFr' => '']);

        self::withARealMailer(static fn () => $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']));

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        $problem = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['title', 'status', 'detail', 'code', 'blockers'], array_keys($problem));
        self::assertSame('content.shop_not_ready', $problem['code']);
        self::assertSame(['shipping', 'legalNotice', 'privacy'], $problem['blockers']);
        self::assertSame('The shop cannot open yet: 3 things still block it.', $problem['detail']);
        self::assertNull(self::getContainer()->get(PublishedSiteRepository::class)->current());

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full'], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['mail', 'shipping', 'legalNotice', 'privacy'], Json::decode((string) $client->getResponse()->getContent())['blockers']);
    }

    public function testAnOpenShopStaysOpenWhenAFullPublicationIsRefused(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);
        self::editIntro($client, 'Frogs in the ferns');
        self::openTheShop($client);
        self::editIntro($client, 'Snails on the moss');
        $client->jsonRequest('PUT', '/api/admin/shipping-zones/france', ['price' => 650, 'freeFrom' => null, 'enabled' => false]);

        self::withARealMailer(static fn () => $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['shipping'], Json::decode((string) $client->getResponse()->getContent())['blockers']);
        $client->jsonRequest('GET', '/api/admin/publication');
        $publication = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame([true, true], [$publication['open'], $publication['pendingChanges']]);
        $client->getCookieJar()->clear();
        self::assertSame('Frogs in the ferns', Json::string(Json::decode($client->request('GET', '/en/')->filter('#site-content')->text()), 'data', 'intro'));
    }

    public function testTheNoteCanAlwaysBePublished(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);

        self::assertResponseIsSuccessful();
        self::assertSame(['note', false], [Json::decode((string) $client->getResponse()->getContent())['page'], Json::decode((string) $client->getResponse()->getContent())['open']]);
    }

    public function testAFrenchOrShopTextAloneWaitsForPublication(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
        self::assertFalse(Json::decode((string) $client->getResponse()->getContent())['pendingChanges']);

        $client->jsonRequest('PUT', '/api/admin/texts/gallery', ['title' => 'Recent drawings', 'titleFr' => 'Dessins récents']);
        self::assertTrue(self::pendingChanges($client));

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
        self::assertFalse(Json::decode((string) $client->getResponse()->getContent())['pendingChanges']);

        $client->jsonRequest('PUT', '/api/admin/texts/shop-notes', ['announcement' => 'Free shipping from 40 €']);
        self::assertTrue(self::pendingChanges($client));
    }

    public function testPublishingFreezesTheShopTextsNextToThePage(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Frogs in the ferns', 'metaDescription' => 'Drawings', 'introFr' => 'Grenouilles dans les fougères']);
        $client->jsonRequest('PUT', '/api/admin/texts/shop-notes', ['priceNoteFr' => 'TVA non applicable']);

        self::stockTheDemo($client);
        self::openTheShop($client);
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Snails on the moss', 'metaDescription' => 'Drawings', 'introFr' => 'Escargots sur la mousse']);

        $site = self::getContainer()->get(PublishedSiteRepository::class)->current();
        self::assertNotNull($site);
        $shop = Json::decode((string) $site->shopContent());
        self::assertSame(['Frogs in the ferns', 'Grenouilles dans les fougères'], [Json::string($shop, 'intro', 'en'), Json::string($shop, 'intro', 'fr')]);
        self::assertSame(['TVA non applicable', 'TVA non applicable'], [Json::string($shop, 'priceNote', 'en'), Json::string($shop, 'priceNote', 'fr')]);
        self::assertSame(['Shop on Etsy', 'Shop on Etsy'], [Json::string($shop, 'links', 0, 'title', 'en'), Json::string($shop, 'links', 0, 'title', 'fr')]);
        self::assertSame('Frogs in the ferns', Json::string(Json::decode($site->content()), 'intro'));
        self::assertStringNotContainsString('Grenouilles', $site->content());
        self::assertStringNotContainsString('TVA', $site->content());
    }

    public function testShowingTheGrowingNoteClosesTheShop(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);
        self::openTheShop($client);

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);

        self::assertResponseIsSuccessful();
        self::assertSame(['note', false], [Json::decode((string) $client->getResponse()->getContent())['page'], Json::decode((string) $client->getResponse()->getContent())['open']]);
        $crawler = $client->request('GET', '/');
        self::assertSame('note', $crawler->filter('#site')->attr('data-page'));
    }

    public function testAPublishedImageOutlivesItsDeletionFromTheDraftUntilTheNextPublication(): void
    {
        $client = self::signedInClient();
        $client->request('POST', '/api/admin/artworks', files: ['image' => self::image()], server: ['HTTP_ACCEPT' => 'application/json']);
        $artwork = Json::decode((string) $client->getResponse()->getContent());
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);

        $client->jsonRequest('DELETE', '/api/admin/artworks/'.Json::string($artwork, 'id'));
        $client->request('GET', Json::string($artwork, 'url'));
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
        $client->request('GET', Json::string($artwork, 'url'));
        self::assertResponseStatusCodeSame(404);
    }

    public function testPublishingNeedsAnAdmin(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);

        self::assertResponseStatusCodeSame(401);
    }

    private static function pendingChanges(KernelBrowser $client): bool
    {
        $client->jsonRequest('GET', '/api/admin/publication');

        return true === Json::at(Json::decode((string) $client->getResponse()->getContent()), 'pendingChanges');
    }

    private static function editIntro(KernelBrowser $client, string $intro): void
    {
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => $intro, 'metaDescription' => 'Drawings']);
        self::assertResponseIsSuccessful();
    }

    private static function image(): UploadedFile
    {
        $copy = tempnam(sys_get_temp_dir(), 'artwork');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 2).'/Fixtures/large-fern.png', $copy);

        return new UploadedFile($copy, 'fern.png', 'image/png', test: true);
    }
}
