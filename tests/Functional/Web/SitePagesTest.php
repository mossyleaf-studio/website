<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Domain\Content\PublishedSiteRepository;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Functional\Content\OpensTheShop;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SitePagesTest extends WebTestCase
{
    use OpensTheShop;
    use PushesCatalogue;
    use SignsInClient;

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testBeforeAnyPublicationTheHomePageShowsTheGrowingNote(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('title', 'mossyleaf.studio');
        self::assertSame('mossyleaf.studio', $crawler->filter('#site > .prerender > header.prerender__band h1')->text());
        self::assertCount(0, $crawler->filter('#site header p, #site header img'));
        self::assertSame('Illustrations of little critters among leaves and moss, drawn by hand.', $crawler->filter('#site main .prerender__intro p')->text());
        self::assertSame('The website is still growing', $crawler->filter('#site main section.prerender__note[aria-labelledby="note-title"] h2')->text());
        self::assertCount(1, $crawler->filter('#site .prerender__note > .prerender__tape'));
        self::assertCount(1, $crawler->filter('#site .prerender > footer.prerender__footer'));
        self::assertSame('index, follow, max-image-preview:large', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('#cbd3a0', $crawler->filter('meta[name="theme-color"]')->attr('content'));
        self::assertSame('note', $crawler->filter('#site')->attr('data-page'));
        self::assertNull($crawler->filter('#site')->attr('data-draft'));
        $content = Json::decode($crawler->filter('#site-content')->text());
        self::assertSame('The website is still growing', Json::string($content, 'homeNote', 'title'));
    }

    public function testTheHomePageDataKeepsExactlyTheKeysItHadBeforeTheFrenchTexts(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Little critters.', 'metaDescription' => 'Drawings', 'introFr' => 'Petites bêtes.', 'metaDescriptionFr' => 'Dessins', 'searchTitleFr' => 'Illustrations']);
        $client->jsonRequest('PUT', '/api/admin/texts/about', ['title' => 'About', 'text' => 'A tiny studio.', 'titleFr' => 'À propos', 'textFr' => 'Un studio.']);
        $client->jsonRequest('PUT', '/api/admin/texts/gallery', ['title' => 'Drawings', 'titleFr' => 'Dessins récents']);
        $client->jsonRequest('PUT', '/api/admin/texts/shop-notes', ['announcement' => 'Free shipping', 'announcementFr' => 'Livraison offerte', 'priceNote' => 'VAT not applicable', 'priceNoteFr' => 'TVA non applicable']);
        $client->jsonRequest('POST', '/api/admin/links', ['title' => 'Ko-fi', 'description' => 'Buy a coffee', 'url' => 'https://ko-fi.com/mossyleaf', 'tape' => 'leaf', 'titleFr' => 'Offrir un café', 'descriptionFr' => 'Sur Ko-fi']);
        foreach (['Une fougère', 'Une mousse'] as $index => $altFr) {
            $copy = tempnam(sys_get_temp_dir(), 'artwork');
            self::assertIsString($copy);
            copy(\dirname(__DIR__, 2).'/Fixtures/large-fern.png', $copy);
            $client->request('POST', '/api/admin/artworks', ['alt' => 'A fern'], ['image' => new UploadedFile($copy, 'fern.png', 'image/png', test: true)], ['HTTP_ACCEPT' => 'application/json']);
            $id = Json::string(Json::decode((string) $client->getResponse()->getContent()), 'id');
            $client->jsonRequest('PUT', '/api/admin/artworks/'.$id, ['alt' => 'A fern', 'altFr' => $altFr]);
            if (0 === $index) {
                $client->jsonRequest('PUT', "/api/admin/artworks/{$id}/featured");
            }
        }

        self::stockTheDemo($client);
        self::readyTheShop($client);
        foreach ([null, 'note', 'full'] as $published) {
            if (null !== $published) {
                self::publish($client, $published);
            }
            $source = 'full' === $published
                ? (self::getContainer()->get(PublishedSiteRepository::class)->current()?->content() ?? '')
                : $client->request('GET', '/')->filter('#site-content')->text();
            $content = Json::decode($source);

            self::assertSame(['studioName', 'intro', 'metaDescription', 'pageTitle', 'logo', 'homeNote', 'about', 'featured', 'gallery', 'links', 'fonts'], array_keys($content));
            self::assertSame(['title', 'text'], array_keys(Json::array($content, 'homeNote')));
            self::assertSame(['title', 'paragraphs'], array_keys(Json::array($content, 'about')));
            self::assertSame(['id', 'url', 'alt', 'width', 'height', 'featured'], array_keys(Json::array($content, 'featured')));
            self::assertSame(['title', 'artworks'], array_keys(Json::array($content, 'gallery')));
            self::assertSame(['id', 'url', 'alt', 'width', 'height', 'featured'], array_keys(Json::array($content, 'gallery', 'artworks', 0)));
            self::assertSame(['id', 'title', 'description', 'url', 'tape'], array_keys(Json::array($content, 'links', 2)));
            self::assertSame(['heading', 'body'], array_keys(Json::array($content, 'fonts')));
            self::assertSame(['Little critters.', 'About', 'Drawings', 'Ko-fi', 'A fern'], [Json::string($content, 'intro'), Json::string($content, 'about', 'title'), Json::string($content, 'gallery', 'title'), Json::string($content, 'links', 2, 'title'), Json::string($content, 'featured', 'alt')]);
            self::assertDoesNotMatchRegularExpression('/bêtes|Dessins|Illustrations|propos|Un studio|Livraison|shipping|TVA|VAT|café|Sur Ko-fi|fougère|mousse/u', 'full' === $published ? $source : (string) $client->getResponse()->getContent());
        }
    }

    public function testPublicPagesStartNoSession(): void
    {
        $client = self::createClient();

        foreach (['/', '/robots.txt', '/sitemap.xml'] as $url) {
            $client->request('GET', $url);

            self::assertResponseIsSuccessful($url);
            self::assertResponseNotHasHeader('Set-Cookie', $url);
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('public'), $url);
        }
    }

    public function testTheDraftPagesAreForAdminsOnly(): void
    {
        $client = self::createClient();

        foreach (['/beta/', '/beta/note/'] as $url) {
            $client->request('GET', $url);
            self::assertResponseRedirects('/login');
        }
    }

    public function testAdminsPreviewTheNoteHiddenFromSearchEnginesWithTheirWayBack(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/beta/note/');

        self::assertResponseIsSuccessful();
        self::assertSame('note', $crawler->filter('#site')->attr('data-page'));
        self::assertSame('', $crawler->filter('#site')->attr('data-draft'));
        self::assertSame(['/beta/en/', '/beta/note/', '/admin'], [$crawler->filter('#site')->attr('data-shop'), $crawler->filter('#site')->attr('data-note'), $crawler->filter('#site')->attr('data-admin')]);
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertCount(0, $crawler->filter('#site nav, #site section[aria-labelledby="gallery-title"], #site section[aria-labelledby="about-title"]'));
    }

    public function testTheOldFullPageIsGoneAndItsAddressOpensTheShopPreview(): void
    {
        $client = self::signedInClient();

        foreach (['fr' => '/beta/fr/', 'fr-FR,fr;q=0.9,en;q=0.8' => '/beta/fr/', 'en' => '/beta/en/', 'de' => '/beta/en/'] as $language => $target) {
            $client->request('GET', '/beta/', server: ['HTTP_ACCEPT_LANGUAGE' => $language]);

            self::assertResponseRedirects($target, 302, $language);
        }
        $client->request('GET', '/beta/');
        self::assertResponseRedirects('/beta/en/', 302);
    }

    public function testTheVisiblePageIsTheNoteUntilTheShopOpens(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);
        self::readyTheShop($client);

        foreach ([null, 'note'] as $published) {
            if (null !== $published) {
                $client->loginUser(SecurityUser::fromUser(self::createUser()));
                self::publish($client, $published);
            }
            $crawler = self::visitor($client)->request('GET', '/');
            self::assertResponseIsSuccessful();
            self::assertSame('note', $crawler->filter('#site')->attr('data-page'));
        }
    }

    public function testOnceTheShopIsOpenTheHomeSendsVisitorsToTheShopInTheirLanguage(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);
        self::openTheShop($client);
        $visitor = self::visitor($client);

        foreach (['fr' => '/fr/', 'fr-FR,fr;q=0.9,en;q=0.8' => '/fr/', 'en' => '/en/', 'de' => '/en/', 'en-GB,en;q=0.9,fr;q=0.8' => '/en/'] as $language => $target) {
            $visitor->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => $language]);

            self::assertResponseRedirects($target, 302, $language);
            self::assertContains('Accept-Language', $visitor->getResponse()->getVary(), $language);
            self::assertResponseNotHasHeader('Set-Cookie', $language);
        }

        $visitor->request('GET', '/');
        self::assertResponseRedirects('/en/', 302);
        self::assertContains('Accept-Language', $visitor->getResponse()->getVary());
        self::assertResponseNotHasHeader('Set-Cookie');
    }

    public function testPagesStayUpWhenTheSameKernelServesSeveralRequests(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();

        foreach (['/', '/beta/note/', '/', '/beta/fr/', '/beta/note/'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
        }
    }

    public function testTheDraftAddressWithoutSlashRedirects(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/beta');

        self::assertResponseRedirects('http://localhost/beta/', 301);
    }

    public function testEditedTextsShowEscaped(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Ferns </script><b>and</b> frogs', 'metaDescription' => 'Drawings']);
        self::assertResponseIsSuccessful();

        $crawler = $client->request('GET', '/beta/note/');

        self::assertStringNotContainsString('</script><b>', (string) $client->getResponse()->getContent());
        self::assertSame('Ferns </script><b>and</b> frogs', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'intro'));
        self::assertSame('Drawings', $crawler->filter('meta[name="description"]')->attr('content'));
    }

    public function testTheChosenFontsReachVisitorsOnceThePageIsPublished(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
        $client->jsonRequest('PUT', '/api/admin/texts/fonts/heading', ['family' => 'Caveat']);
        $client->jsonRequest('PUT', '/api/admin/texts/fonts/body', ['family' => 'Patrick Hand']);
        self::assertResponseIsSuccessful();
        $style = "--font-display: 'Caveat', cursive; --font-display-weight: 700;--font-body: 'Patrick Hand', cursive; --font-body-weight: 400;";

        $crawler = $client->request('GET', '/beta/note/');
        self::assertSame($style, $crawler->filter('html')->attr('style'));
        self::assertCount(2, $crawler->filter('link[rel="stylesheet"][href^="/media/fonts/"]'));

        $crawler = $client->request('GET', '/');
        self::assertNull($crawler->filter('html')->attr('style'));
        self::assertCount(0, $crawler->filter('link[href^="/media/fonts/"]'));

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
        $crawler = $client->request('GET', '/');
        self::assertSame($style, $crawler->filter('html')->attr('style'));
    }

    private static function visitor(KernelBrowser $client): KernelBrowser
    {
        $client->getCookieJar()->clear();

        return $client;
    }
}
