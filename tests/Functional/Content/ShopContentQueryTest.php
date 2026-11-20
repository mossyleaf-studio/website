<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Application\Content\ContentQueries;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShopContentQueryTest extends WebTestCase
{
    use SignsInClient;

    public function testTheDraftIsReadLiveAndThePublicReadsTheFrozenTexts(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();
        $queries = self::getContainer()->get(ContentQueries::class);

        self::assertSame('', $queries->shop(false)->studioName);

        $client->jsonRequest('PUT', '/api/admin/texts/about', ['title' => 'About', 'text' => 'Published.', 'titleFr' => 'À propos', 'textFr' => 'Publié.']);
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);
        $client->jsonRequest('PUT', '/api/admin/texts/about', ['title' => 'About', 'text' => 'Draft.', 'titleFr' => 'À propos', 'textFr' => 'Brouillon.']);

        self::assertSame(['Published.', 'Publié.'], [$queries->shop(false)->aboutText->en, $queries->shop(false)->aboutText->fr]);
        self::assertSame(['Draft.', 'Brouillon.'], [$queries->shop(true)->aboutText->en, $queries->shop(true)->aboutText->fr]);
    }
}
