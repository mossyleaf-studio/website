<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Content\ContentQueries;
use App\Domain\Content\PublishedSiteRepository;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class LegalPagesApiTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use SignsInClient;

    public function testTheFourPagesAreThereEmptyFromTheStart(): void
    {
        $client = self::signedInClient();

        self::assertEquals([
            ['kind' => 'terms', 'text' => '', 'textFr' => '', 'updatedAt' => null, 'preview' => ['en' => '/beta/en/legal/terms', 'fr' => '/beta/fr/legal/terms']],
            ['kind' => 'legal-notice', 'text' => '', 'textFr' => '', 'updatedAt' => null, 'preview' => ['en' => '/beta/en/legal/legal-notice', 'fr' => '/beta/fr/legal/legal-notice']],
            ['kind' => 'privacy', 'text' => '', 'textFr' => '', 'updatedAt' => null, 'preview' => ['en' => '/beta/en/legal/privacy', 'fr' => '/beta/fr/legal/privacy']],
            ['kind' => 'shipping-returns', 'text' => '', 'textFr' => '', 'updatedAt' => null, 'preview' => ['en' => '/beta/en/legal/shipping-returns', 'fr' => '/beta/fr/legal/shipping-returns']],
        ], self::pages($client));
    }

    public function testAPageIsWrittenInBothLanguages(): void
    {
        self::mockTime('2027-06-12 10:00:00 UTC');
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/legal-pages/legal-notice', ['text' => " ## Publisher\r\nmossyleaf.studio ", 'textFr' => "## Éditeur\nmossyleaf.studio"]);

        self::assertResponseIsSuccessful();
        $page = ['kind' => 'legal-notice', 'text' => "## Publisher\r\nmossyleaf.studio", 'textFr' => "## Éditeur\nmossyleaf.studio", 'updatedAt' => '2027-06-12T10:00:00+00:00', 'preview' => ['en' => '/beta/en/legal/legal-notice', 'fr' => '/beta/fr/legal/legal-notice']];
        self::assertEquals($page, Json::decode((string) $client->getResponse()->getContent()));
        self::assertEquals($page, Json::array(self::pages($client), 1));

        $client->jsonRequest('PUT', '/api/admin/legal-pages/legal-notice', ['text' => '', 'textFr' => '']);
        self::assertResponseIsSuccessful();
        self::assertSame(['', ''], [Json::string(self::pages($client), 1, 'text'), Json::string(self::pages($client), 1, 'textFr')]);

        $client->jsonRequest('PUT', '/api/admin/legal-pages/legal-notice', ['text' => 'Short', 'textFr' => str_repeat('é', 30001)]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['textFr'], array_column(Json::array(Json::decode((string) $client->getResponse()->getContent()), 'violations'), 'propertyPath'));
    }

    public function testPublishingFreezesTheFourPagesWhichALaterEditLeavesAlone(): void
    {
        self::mockTime('2027-06-12 22:30:00 UTC');
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
        $client->jsonRequest('PUT', '/api/admin/legal-pages/terms', ['text' => 'Pay first.', 'textFr' => '']);
        $client->jsonRequest('GET', '/api/admin/publication');
        self::assertTrue(Json::at(Json::decode((string) $client->getResponse()->getContent()), 'pendingChanges'));

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
        self::assertResponseIsSuccessful();
        $client->jsonRequest('PUT', '/api/admin/legal-pages/terms', ['text' => 'Pay later.', 'textFr' => 'Payez plus tard.']);

        $site = self::getContainer()->get(PublishedSiteRepository::class)->current();
        self::assertNotNull($site);
        self::assertEquals([
            ['kind' => 'terms', 'text' => ['en' => 'Pay first.', 'fr' => 'Pay first.'], 'updatedAt' => '2027-06-13'],
            ['kind' => 'legal-notice', 'text' => ['en' => '', 'fr' => ''], 'updatedAt' => null],
            ['kind' => 'privacy', 'text' => ['en' => '', 'fr' => ''], 'updatedAt' => null],
            ['kind' => 'shipping-returns', 'text' => ['en' => '', 'fr' => ''], 'updatedAt' => null],
        ], Json::array(Json::decode((string) $site->shopContent()), 'legal'));
        self::assertStringNotContainsString('Pay first', $site->content());
        self::assertSame('Pay first.', self::getContainer()->get(ContentQueries::class)->shop(false)->legal[0]->text->fr);
        self::assertSame('Payez plus tard.', self::getContainer()->get(ContentQueries::class)->shop(true)->legal[0]->text->fr);
    }

    public function testAnUnknownKindIsNotFound(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/legal-pages/cookies', ['text' => 'Hello', 'textFr' => '']);

        self::assertResponseStatusCodeSame(404);
    }

    /** @return list<mixed> */
    private static function pages(KernelBrowser $client): array
    {
        $client->jsonRequest('GET', '/api/admin/legal-pages');
        self::assertResponseIsSuccessful();

        return array_values(Json::decode((string) $client->getResponse()->getContent()));
    }
}
