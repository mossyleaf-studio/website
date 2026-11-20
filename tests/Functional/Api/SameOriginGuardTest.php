<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SameOriginGuardTest extends WebTestCase
{
    use SignsInClient;

    public function testAnEncodedPathCannotSlipPastTheGuard(): void
    {
        $client = self::signedInClient();

        foreach (['/api/admin/links', '/%61pi/admin/links', '/%61%70%69/admin/links'] as $url) {
            $client->jsonRequest('POST', $url, ['title' => 'Stolen', 'description' => 'Stolen', 'url' => 'https://evil.test/', 'tape' => 'leaf'], ['HTTP_ORIGIN' => 'https://evil.test']);

            self::assertResponseStatusCodeSame(403, $url);
        }

        $client->jsonRequest('GET', '/api/admin/links');
        self::assertNotContains('Stolen', array_column(Json::decode((string) $client->getResponse()->getContent()), 'title'));
    }

    public function testASameOriginWriteStillPasses(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/%61pi/admin/links', ['title' => 'Ko-fi', 'description' => 'Coffee', 'url' => 'https://ko-fi.com/mossyleaf', 'tape' => 'leaf'], ['HTTP_ORIGIN' => 'http://localhost', 'HTTP_SEC_FETCH_SITE' => 'same-origin']);

        self::assertResponseStatusCodeSame(201);
    }

    public function testADomainErrorCarriesTheKeyOfItsMessage(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('DELETE', '/api/admin/links/01JD3K7Q0000000000000000F0');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('shared.not_found', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'code'));
    }
}
