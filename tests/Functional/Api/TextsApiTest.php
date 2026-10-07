<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

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

    public function testTheFontsStartAsGaeguAndKalamAndCanBeChosen(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/admin/texts');
        $texts = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['gaegu', 'kalam'], [Json::string($texts, 'headingFont'), Json::string($texts, 'bodyFont')]);

        $client->jsonRequest('PUT', '/api/admin/texts/fonts', ['heading' => 'caveat', 'body' => 'nunito']);

        self::assertResponseIsSuccessful();
        $texts = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['caveat', 'nunito'], [Json::string($texts, 'headingFont'), Json::string($texts, 'bodyFont')]);
    }

    public function testAnUnknownFontIsRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/texts/fonts', ['heading' => 'comic-sans', 'body' => 'kalam']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testWritesFromAnotherOriginAreRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/texts/gallery', ['title' => 'Stolen'], ['HTTP_ORIGIN' => 'https://evil.test']);

        self::assertResponseStatusCodeSame(403);
    }
}
