<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalogue;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Catalogue\CategoryView;
use App\Application\Catalogue\ProductView;
use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Application\Catalogue\ReplaceCatalogue\PushedVariant;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogue;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogueHandler;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

trait PushesCatalogue
{
    /**
     * @param list<PushedProduct> $products
     *
     * @return array<string, string>
     */
    private static function push(array $products): array
    {
        self::getContainer()->get(ReplaceCatalogueHandler::class)(new ReplaceCatalogue($products));

        return array_column(array_map(static fn (ProductView $product): array => [$product->slug, $product->id], self::getContainer()->get(CatalogueQueries::class)->products()), 1, 0);
    }

    /** @param array<string, int>|int $stocks */
    private static function product(int $number, string $category, string $name, int $price, array|int $stocks = 5): PushedProduct
    {
        $reference = strtoupper(substr($category, 0, 3).'-'.$name);
        $variants = \is_int($stocks)
            ? [new PushedVariant(null, $reference, $stocks)]
            : array_map(static fn (string $variant, int $stock): PushedVariant => new PushedVariant($variant, $reference.'-'.$variant, $stock), array_keys($stocks), array_values($stocks));

        return new PushedProduct(new Ulid(\sprintf('01JD3K7Q%018d', $number)), $reference, $name, $category, $category.' '.$name, $price, $variants);
    }

    /** @return list<PushedProduct> */
    private static function demo(): array
    {
        return [
            self::product(1, 'Prints', 'Fox', 1500, ['A4' => 12, 'A5' => 0]),
            self::product(2, 'Prints', 'Owl', 1200, 0),
            self::product(3, 'Stickers', 'Moth', 300, 4),
            self::product(4, 'Prints', 'Hare', 0, 5),
            self::product(5, 'Cards', 'Bee', 500, 3),
        ];
    }

    /** @return array<string, string> */
    private static function stockTheDemo(KernelBrowser $client): array
    {
        $ids = self::push(self::demo());
        foreach (['prints-fox', 'prints-owl', 'prints-hare', 'cards-bee'] as $slug) {
            self::show($client, $ids[$slug]);
        }
        self::push(array_slice(self::demo(), 0, 4));

        return $ids;
    }

    private static function show(KernelBrowser $client, string $id): void
    {
        $client->jsonRequest('PUT', "/api/admin/products/{$id}/visibility");
        self::assertResponseIsSuccessful();
    }

    /** @param array<string, string> $texts */
    private static function describe(KernelBrowser $client, string $id, string $slug, array $texts): void
    {
        $client->jsonRequest('PUT', "/api/admin/products/{$id}", $texts + ['title' => '', 'titleFr' => '', 'description' => '', 'descriptionFr' => '', 'slug' => $slug]);
        self::assertResponseIsSuccessful();
    }

    private static function label(KernelBrowser $client, string $category, string $label, string $labelFr): void
    {
        $id = array_find(self::getContainer()->get(CatalogueQueries::class)->categories(), static fn (CategoryView $view): bool => $view->name === $category)?->id;
        $client->jsonRequest('PUT', "/api/admin/categories/{$id}", ['label' => $label, 'labelFr' => $labelFr]);
        self::assertResponseIsSuccessful();
    }

    private static function photo(KernelBrowser $client, string $id): void
    {
        $copy = tempnam(sys_get_temp_dir(), 'photo');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 2).'/Fixtures/large-fern.png', $copy);
        $client->request('POST', "/api/admin/products/{$id}/photos", files: ['image' => new UploadedFile($copy, 'fern.png', 'image/png', test: true)], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(201);
    }
}
