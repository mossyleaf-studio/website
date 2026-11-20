<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Application\Catalogue\ReplaceCatalogue\PushedVariant;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogue;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogueHandler;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class CategoriesApiTest extends WebTestCase
{
    use SignsInClient;

    public function testCategoriesAreListedInTheirOrder(): void
    {
        $client = self::signedInClient();
        self::push();

        $client->jsonRequest('GET', '/api/admin/categories');

        self::assertResponseIsSuccessful();
        $categories = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame([['Prints', '', ''], ['Stickers', '', '']], array_map(static fn (mixed $category): array => [Json::string($category, 'name'), Json::string($category, 'label'), Json::string($category, 'labelFr')], $categories));
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', Json::string($categories, 0, 'id'));
    }

    public function testACategoryIsLabelledInBothLanguages(): void
    {
        $client = self::signedInClient();
        [$prints] = self::push();

        $client->jsonRequest('PUT', "/api/admin/categories/{$prints}", ['label' => ' Art prints ', 'labelFr' => 'Affiches']);

        self::assertResponseIsSuccessful();
        $category = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['Prints', 'Art prints', 'Affiches'], [$category['name'], $category['label'], $category['labelFr']]);
        $client->jsonRequest('GET', '/api/admin/products');
        self::assertSame('Affiches', Json::string(Json::decode((string) $client->getResponse()->getContent()), 0, 'category', 'labelFr'));
    }

    public function testALabelHoldsSixtyCharactersPerLanguage(): void
    {
        $client = self::signedInClient();
        [$prints] = self::push();

        $client->jsonRequest('PUT', "/api/admin/categories/{$prints}", ['label' => str_repeat('a', 60), 'labelFr' => str_repeat('é', 60)]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('PUT', "/api/admin/categories/{$prints}", ['label' => 'Prints', 'labelFr' => str_repeat('é', 61)]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testCategoriesAreReordered(): void
    {
        $client = self::signedInClient();
        [$prints, $stickers] = self::push();

        $client->jsonRequest('PUT', '/api/admin/categories/order', ['ids' => [$stickers, $prints]]);

        self::assertResponseIsSuccessful();
        self::assertSame([$stickers, $prints], array_column(Json::decode((string) $client->getResponse()->getContent()), 'id'));

        $client->jsonRequest('PUT', '/api/admin/categories/order', ['ids' => [$stickers]]);
        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routes(): iterable
    {
        yield 'list' => ['GET', '/api/admin/categories'];
        yield 'label' => ['PUT', '/api/admin/categories/'.new Ulid()];
        yield 'reorder' => ['PUT', '/api/admin/categories/order'];
    }

    #[DataProvider('routes')]
    public function testEveryRouteNeedsASignedInEditor(string $method, string $url): void
    {
        $client = self::createClient();

        $client->jsonRequest($method, $url);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return list<string>
     */
    private static function push(): array
    {
        self::getContainer()->get(ReplaceCatalogueHandler::class)(new ReplaceCatalogue([
            new PushedProduct(new Ulid(), 'PRT-FOX', 'Fox', 'Prints', 'Prints Fox', 1500, [new PushedVariant(null, 'PRT-FOX', 1)]),
            new PushedProduct(new Ulid(), 'STK-OWL', 'Owl', 'Stickers', 'Stickers Owl', 300, [new PushedVariant(null, 'STK-OWL', 4)]),
        ]));
        $client = self::getClient();
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->jsonRequest('GET', '/api/admin/categories');

        return array_values(array_map(static fn (mixed $category): string => Json::string($category, 'id'), Json::decode((string) $client->getResponse()->getContent())));
    }
}
