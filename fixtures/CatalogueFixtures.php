<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Application\Catalogue\DescribeProduct\DescribeProduct;
use App\Application\Catalogue\DescribeProduct\DescribeProductHandler;
use App\Application\Catalogue\LabelCategory\LabelCategory;
use App\Application\Catalogue\LabelCategory\LabelCategoryHandler;
use App\Application\Catalogue\LabelCollection\LabelCollection;
use App\Application\Catalogue\LabelCollection\LabelCollectionHandler;
use App\Application\Catalogue\PickProduct\PickProduct;
use App\Application\Catalogue\PickProduct\PickProductHandler;
use App\Application\Catalogue\ReplaceCatalogue\PushedCollection;
use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Application\Catalogue\ReplaceCatalogue\PushedVariant;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogue;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogueHandler;
use App\Application\Catalogue\ShowCollection\ShowCollection;
use App\Application\Catalogue\ShowCollection\ShowCollectionHandler;
use App\Application\Catalogue\ShowProduct\ShowProduct;
use App\Application\Catalogue\ShowProduct\ShowProductHandler;
use App\Application\Catalogue\UploadProductPhoto\UploadProductPhoto;
use App\Application\Catalogue\UploadProductPhoto\UploadProductPhotoHandler;
use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\CategoryRepository;
use App\Domain\Catalogue\Collection;
use App\Domain\Catalogue\CollectionRepository;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Ulid;

/**
 * @phpstan-type FixtureProduct array{category: string, name: string, price: int, variants: list<array{0: ?string, 1: string, 2: int}>, title: string, titleFr: string, description: string, descriptionFr: string, photos: list<string>, visible: bool, retired?: bool, collection?: string}
 */
final class CatalogueFixtures extends Fixture
{
    private const array CATEGORIES = [
        'Prints' => ['Prints', 'Tirages'],
        'Stickers' => ['Stickers and tape', 'Stickers et masking tape'],
        'Cards' => ['Paper goods', 'Papeterie'],
    ];

    private const array COLLECTIONS = [
        'Forest critters' => ['Forest critters', 'Bestioles de la forêt', true],
        'Autumn market' => ['', '', false],
    ];

    private const array PICKED = ['PRT-001', 'CRD-001'];

    /** @var array<string, FixtureProduct> */
    private const array PRODUCTS = [
        'PRT-001' => [
            'collection' => 'Forest critters', 'category' => 'Prints', 'name' => 'Moss print', 'price' => 1800, 'variants' => [['A4', 'PRT-001-A4', 12], ['A3', 'PRT-001-A3', 5]],
            'title' => 'Moss print', 'titleFr' => 'Tirage Mousse',
            'description' => 'A watercolour of moss and fallen leaves, printed on cotton paper.',
            'descriptionFr' => 'Une aquarelle de mousse et de feuilles tombées, imprimée sur papier coton.',
            'photos' => ['c2da5a', 'e0e3be'], 'visible' => true,
        ],
        'PRT-002' => [
            'category' => 'Prints', 'name' => 'Fern print', 'price' => 2200, 'variants' => [[null, 'PRT-002', 8]],
            'title' => 'Fern print', 'titleFr' => 'Tirage Fougère',
            'description' => 'Unrolling fern fronds in green ink, A3 on 250 g paper.',
            'descriptionFr' => 'Des frondes de fougère qui se déroulent à l\'encre verte, A3 sur papier 250 g.',
            'photos' => ['b2e0ff'], 'visible' => true,
        ],
        'STK-001' => [
            'collection' => 'Forest critters', 'category' => 'Stickers', 'name' => 'Meadow sticker sheet', 'price' => 600, 'variants' => [[null, 'STK-001', 0]],
            'title' => 'Meadow sticker sheet', 'titleFr' => 'Planche de stickers Prairie',
            'description' => 'Twelve waterproof vinyl stickers cut from the sketchbook.',
            'descriptionFr' => 'Douze stickers en vinyle waterproof découpés dans le carnet de croquis.',
            'photos' => ['ffeddb'], 'visible' => true,
        ],
        'STK-002' => [
            'category' => 'Stickers', 'name' => 'Leaf sticker pack', 'price' => 400, 'variants' => [[null, 'STK-002', 30]],
            'title' => 'Leaf sticker pack', 'titleFr' => 'Pochette de stickers Feuilles',
            'description' => 'Five small stickers for a notebook or a bottle.',
            'descriptionFr' => 'Cinq petits stickers pour un cahier ou une gourde.',
            'photos' => [], 'visible' => true,
        ],
        'STK-003' => [
            'category' => 'Stickers', 'name' => 'Washi tape set', 'price' => 900, 'variants' => [['Green', 'STK-003-G', 14], ['Blue', 'STK-003-B', 2]],
            'title' => 'Washi tape set', 'titleFr' => '',
            'description' => 'Two rolls of washi tape printed with the studio leaves.',
            'descriptionFr' => '',
            'photos' => ['a58361'], 'visible' => true,
        ],
        'CRD-001' => [
            'collection' => 'Forest critters', 'category' => 'Cards', 'name' => 'Postcard set', 'price' => 1200, 'variants' => [[null, 'CRD-001', 20]],
            'title' => 'Postcard set', 'titleFr' => 'Lot de cartes postales',
            'description' => 'Six postcards with an envelope each, to send a bit of forest.',
            'descriptionFr' => 'Six cartes postales avec leur enveloppe, pour envoyer un peu de forêt.',
            'photos' => ['e0e3be', 'fef9e7', 'c2da5a'], 'visible' => true,
        ],
        'CRD-002' => [
            'collection' => 'Autumn market', 'category' => 'Cards', 'name' => 'Dew greeting card', 'price' => 450, 'variants' => [[null, 'CRD-002', 40]],
            'title' => 'Dew greeting card', 'titleFr' => 'Carte de voeux Rosée',
            'description' => 'A folded card, blank inside.',
            'descriptionFr' => 'Une carte double, vierge à l\'intérieur.',
            'photos' => [], 'visible' => false,
        ],
        'CRD-003' => [
            'category' => 'Cards', 'name' => 'Wall calendar 2026', 'price' => 1500, 'variants' => [[null, 'CRD-003', 3]],
            'title' => 'Wall calendar 2026', 'titleFr' => 'Calendrier mural 2026',
            'description' => 'Twelve months, twelve drawings.',
            'descriptionFr' => 'Douze mois, douze dessins.',
            'photos' => ['b2e0ff'], 'visible' => true, 'retired' => true,
        ],
    ];

    public function __construct(
        private readonly ReplaceCatalogueHandler $replace,
        private readonly DescribeProductHandler $describe,
        private readonly ShowProductHandler $show,
        private readonly PickProductHandler $pick,
        private readonly UploadProductPhotoHandler $upload,
        private readonly LabelCategoryHandler $label,
        private readonly LabelCollectionHandler $labelCollection,
        private readonly ShowCollectionHandler $showCollection,
        private readonly CollectionRepository $collections,
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->replace->__invoke(new ReplaceCatalogue($this->pushed(self::PRODUCTS)));
        $this->replace->__invoke(new ReplaceCatalogue($this->pushed(array_filter(self::PRODUCTS, static fn (array $product): bool => !($product['retired'] ?? false)))));

        foreach ($this->categories->all() as $category) {
            $this->labelCategory($category);
        }
        foreach ($this->collections->all() as $collection) {
            $this->setUpCollection($collection);
        }
        foreach ($this->products->all() as $product) {
            $this->enrich($product);
        }
    }

    /**
     * @param array<string, FixtureProduct> $products
     *
     * @return list<PushedProduct>
     */
    private function pushed(array $products): array
    {
        $pushed = [];
        foreach ($products as $reference => $product) {
            $pushed[] = new PushedProduct(
                self::sourceId($reference),
                $reference,
                $product['name'],
                $product['category'],
                $product['name'],
                $product['price'],
                array_map(static fn (array $variant): PushedVariant => new PushedVariant(...$variant), $product['variants']),
                collection: isset($product['collection']) ? new PushedCollection(self::sourceId($product['collection']), $product['collection']) : null,
            );
        }

        return $pushed;
    }

    private static function sourceId(string $reference): Ulid
    {
        return Ulid::fromBinary(md5($reference, true));
    }

    private function labelCategory(Category $category): void
    {
        [$en, $fr] = self::CATEGORIES[$category->name()];
        $this->label->__invoke(new LabelCategory($category->id(), $en, $fr));
    }

    private function setUpCollection(Collection $collection): void
    {
        [$en, $fr, $shown] = self::COLLECTIONS[$collection->name()];
        if ('' !== $en || '' !== $fr) {
            $this->labelCollection->__invoke(new LabelCollection($collection->id(), $en, $fr));
        }
        if ($shown) {
            $this->showCollection->__invoke(new ShowCollection($collection->id()));
        }
    }

    private function enrich(Product $product): void
    {
        $data = self::PRODUCTS[$product->reference()];
        $this->describe->__invoke(new DescribeProduct($product->id(), $data['title'], $data['titleFr'], $data['description'], $data['descriptionFr'], $product->slug()));
        foreach ($data['photos'] as $index => $colour) {
            $path = self::swatch($colour, 0 === $index % 2 ? [1200, 900] : [900, 1200]);
            $this->upload->__invoke(new UploadProductPhoto($product->id(), $path));
            unlink($path);
        }
        if ($data['visible'] && !$product->isRetired()) {
            $this->show->__invoke(new ShowProduct($product->id()));
        }
        if (in_array($product->reference(), self::PICKED, true)) {
            $this->pick->__invoke(new PickProduct($product->id()));
        }
    }

    /**
     * @param array{int<1, max>, int<1, max>} $size
     */
    private static function swatch(string $hex, array $size): string
    {
        $image = imagecreatetruecolor($size[0], $size[1]);
        imagefill($image, 0, 0, (int) hexdec($hex));
        $path = sys_get_temp_dir().'/swatch-'.uniqid().'.png';
        imagepng($image, $path);

        return $path;
    }
}
