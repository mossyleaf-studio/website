<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\FakeGoogleFonts;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TextsApiTest extends WebTestCase
{
    use SignsInClient;

    public function testTheTextsStartWithTheStaticSiteCopy(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/admin/texts');

        self::assertResponseIsSuccessful();
        $texts = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame('mossyleaf.studio', Json::string($texts, 'studioName'));
        self::assertSame('About the studio', Json::string($texts, 'aboutTitle'));
    }

    public function testEachSectionIsSavedOnItsOwn(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/texts/home-note', ['title' => 'Back soon', 'text' => 'Moss is growing.']);
        self::assertResponseIsSuccessful();
        $client->jsonRequest('PUT', '/api/admin/texts/about', ['title' => 'Hello', 'text' => "First paragraph.\r\n\r\nSecond paragraph."]);
        self::assertResponseIsSuccessful();
        $client->jsonRequest('PUT', '/api/admin/texts/gallery', ['title' => 'Sketchbook']);
        self::assertResponseIsSuccessful();

        $texts = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['Back soon', 'Moss is growing.', "First paragraph.\n\nSecond paragraph.", 'Sketchbook'], [
            Json::string($texts, 'homeNoteTitle'),
            Json::string($texts, 'homeNoteText'),
            Json::string($texts, 'aboutText'),
            Json::string($texts, 'galleryTitle'),
        ]);
        self::assertSame('mossyleaf.studio', Json::string($texts, 'studioName'));
    }

    public function testBlankAndTooLongTextsAreRefusedPerField(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => '', 'intro' => str_repeat('a', 201), 'metaDescription' => 'ok']);

        self::assertResponseStatusCodeSame(422);
        $violations = Json::array(Json::decode((string) $client->getResponse()->getContent()), 'violations');
        self::assertSame(['studioName', 'intro'], array_column($violations, 'propertyPath'));
    }

    public function testWhitespaceOnlyIsAnEmptyText(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/texts/gallery', ['title' => '   ']);

        self::assertResponseStatusCodeSame(422);
        $violations = Json::array(Json::decode((string) $client->getResponse()->getContent()), 'violations');
        self::assertSame([['title', 'This field cannot be empty.']], array_map(static fn (mixed $violation): array => [Json::string($violation, 'propertyPath'), Json::string($violation, 'title')], $violations));
    }

    public function testAnyGoogleFontIsDownloadedAndServedByTheSite(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/admin/texts');
        $texts = Json::decode((string) $client->getResponse()->getContent());
        self::assertNull(Json::at($texts, 'headingFont'));

        $client->jsonRequest('PUT', '/api/admin/texts/fonts/heading', ['family' => ' caveat ']);

        self::assertResponseIsSuccessful();
        $texts = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['Caveat', 700], [Json::string($texts, 'headingFont', 'family'), Json::int($texts, 'headingFont', 'weight')]);
        $stylesheet = Json::string($texts, 'headingFont', 'stylesheet');
        self::assertMatchesRegularExpression('#^/media/fonts/[0-9A-Z]{26}\.css$#', $stylesheet);

        $client->request('GET', $stylesheet);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/css; charset=utf-8');
        $css = (string) $client->getInternalResponse()->getContent();
        self::assertSame(2, substr_count($css, '@font-face'));
        self::assertStringContainsString("font-family: 'Caveat';", $css);
        self::assertStringContainsString('font-weight: 700;', $css);
        self::assertStringNotContainsString('gstatic', $css);

        self::assertSame(1, preg_match('/url\(([^)]+)\)/', $css, $font));
        $client->request('GET', '/media/fonts/'.$font[1]);
        self::assertResponseHeaderSame('Content-Type', 'font/woff2');
        self::assertSame(FakeGoogleFonts::WOFF2, $client->getInternalResponse()->getContent());
    }

    public function testEachRoleGetsTheClosestWeightTheFontHas(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/texts/fonts/heading', ['family' => 'Patrick Hand']);
        $client->jsonRequest('PUT', '/api/admin/texts/fonts/body', ['family' => 'Caveat']);

        $texts = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame([400, 400], [Json::int($texts, 'headingFont', 'weight'), Json::int($texts, 'bodyFont', 'weight')]);
    }

    public function testAnEmptyNameBringsBackTheSiteFontAndDropsTheDownloadedOne(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/fonts/body', ['family' => 'Caveat']);
        $stylesheet = Json::string(Json::decode((string) $client->getResponse()->getContent()), 'bodyFont', 'stylesheet');

        $client->jsonRequest('PUT', '/api/admin/texts/fonts/body', ['family' => '']);

        self::assertResponseIsSuccessful();
        self::assertNull(Json::at(Json::decode((string) $client->getResponse()->getContent()), 'bodyFont'));
        $client->request('GET', $stylesheet);
        self::assertResponseStatusCodeSame(404);
    }

    public function testFontsGoogleDoesNotKnowOrWithoutLatinLettersAreRefused(): void
    {
        $client = self::signedInClient();

        foreach (['Comic Sans', 'Kanji Only', "Caveat'; } body { color: red"] as $family) {
            $client->jsonRequest('PUT', '/api/admin/texts/fonts/heading', ['family' => $family]);
            self::assertResponseStatusCodeSame(422);
        }
    }

    public function testTheGoogleFontNamesAreListedForTheAdmin(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/admin/fonts');

        self::assertResponseIsSuccessful();
        self::assertContains('Patrick Hand', Json::array(Json::decode((string) $client->getResponse()->getContent()), 'families'));
    }

    public function testWritesFromAnotherOriginAreRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/texts/gallery', ['title' => 'Stolen'], ['HTTP_ORIGIN' => 'https://evil.test']);

        self::assertResponseStatusCodeSame(403);
    }
}
