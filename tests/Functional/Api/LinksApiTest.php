<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class LinksApiTest extends WebTestCase
{
    use SignsInClient;

    public function testTheStaticSiteLinksAreThereFromTheStart(): void
    {
        $client = self::signedInClient();

        self::assertSame(['Shop on Etsy', 'Follow on Instagram'], array_column(self::links($client), 'title'));
    }

    public function testALinkIsCreatedAtTheEndEditedAndDeleted(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/links', ['title' => 'Ko-fi', 'description' => 'Buy a coffee', 'url' => 'https://ko-fi.com/mossyleaf', 'tape' => 'blossom']);
        self::assertResponseStatusCodeSame(201);
        $id = Json::string(Json::decode((string) $client->getResponse()->getContent()), 'id');
        self::assertSame('Ko-fi', Json::string(self::links($client), 2, 'title'));

        $client->jsonRequest('PUT', '/api/admin/links/'.$id, ['title' => 'Ko-fi page', 'description' => 'Buy a coffee', 'url' => 'https://ko-fi.com/mossyleaf', 'tape' => 'leaf']);
        self::assertResponseIsSuccessful();
        self::assertSame('leaf', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'tape'));

        $client->jsonRequest('DELETE', '/api/admin/links/'.$id);
        self::assertResponseStatusCodeSame(204);
        self::assertCount(2, self::links($client));
    }

    public function testOnlyHttpsAddressesAreAccepted(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/links', ['title' => 'Blog', 'description' => 'Old', 'url' => 'http://example.com', 'tape' => 'leaf']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['url'], array_column(Json::array(Json::decode((string) $client->getResponse()->getContent()), 'violations'), 'propertyPath'));
    }

    public function testAnUnknownTapeIsRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/admin/links', ['title' => 'Blog', 'description' => 'Old', 'url' => 'https://example.com', 'tape' => 'glitter']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testLinksAreReordered(): void
    {
        $client = self::signedInClient();
        $ids = array_column(self::links($client), 'id');

        $client->jsonRequest('PUT', '/api/admin/links/order', ['ids' => array_reverse($ids)]);

        self::assertResponseIsSuccessful();
        self::assertSame(['Follow on Instagram', 'Shop on Etsy'], array_column(self::links($client), 'title'));
    }

    public function testAnOrderThatMissesALinkIsRefused(): void
    {
        $client = self::signedInClient();
        $ids = array_column(self::links($client), 'id');

        $client->jsonRequest('PUT', '/api/admin/links/order', ['ids' => [$ids[0]]]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testAnUnknownLinkIsNotFound(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('DELETE', '/api/admin/links/'.new Ulid());

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return list<array<string, string>>
     */
    private static function links(KernelBrowser $client): array
    {
        $client->jsonRequest('GET', '/api/admin/links');
        self::assertResponseIsSuccessful();

        /** @var list<array<string, string>> $links */
        $links = Json::decode((string) $client->getResponse()->getContent());

        return $links;
    }
}
