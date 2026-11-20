<?php

declare(strict_types=1);

namespace App\Tests\Functional\Sales;

use App\Application\AtomicChange;
use App\Application\Catalogue\CatalogueQueries;
use App\Application\Catalogue\ShopVariantView;
use App\Application\Catalogue\VariantView;
use App\Application\Sales\CartLine;
use App\Application\Sales\CartPricer;
use App\Application\Sales\ConfirmPayment\ConfirmPayment;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Application\Sales\EditShippingZone\EditShippingZone;
use App\Application\Sales\EditShippingZone\EditShippingZoneHandler;
use App\Application\Sales\PlaceOrder\PlacedOrder;
use App\Application\Sales\PlaceOrder\PlaceOrder;
use App\Application\Sales\PlaceOrder\PlaceOrderHandler;
use App\Domain\Catalogue\ProductRepository;
use App\Domain\Sales\Delivery;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\Zone;
use App\Domain\Shared\Locale;
use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Support\FakeOrderUrls;
use App\Tests\Support\InMemoryPaymentGateway;
use App\Tests\Support\RecordingLogger;
use App\Tests\Support\RecordingOrderMails;
use App\Tests\Support\SignsInClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

trait PlacesOrders
{
    use PushesCatalogue;
    use SignsInClient;

    private InMemoryPaymentGateway $gateway;
    private RecordingOrderMails $mails;

    private RecordingLogger $logger;

    private function doubles(): void
    {
        $this->gateway = new InMemoryPaymentGateway();
        $this->mails = new RecordingOrderMails(static fn (): int => self::getContainer()->get(EntityManagerInterface::class)->getConnection()->getTransactionNestingLevel());
        $this->logger = new RecordingLogger();
    }

    private static function stockTheShop(): void
    {
        $client = self::signedInClient();
        $ids = self::push(self::shopProducts(3));
        foreach (['prints-fox', 'stickers-moth', 'prints-owl'] as $slug) {
            self::show($client, $ids[$slug]);
        }
        self::describe($client, $ids['prints-fox'], 'prints-fox', ['title' => 'Fox print', 'titleFr' => 'Affiche renard']);
        self::zone(Zone::France, 650, null, true);
        self::zone(Zone::World, 1800, null, true);
    }

    /** @return list<\App\Application\Catalogue\ReplaceCatalogue\PushedProduct> */
    private static function shopProducts(int $moths, bool $foxA4 = true): array
    {
        return [
            self::product(11, 'Prints', 'Fox', 1500, $foxA4 ? ['A4' => 12, 'A5' => 0] : ['A5' => 0]),
            self::product(12, 'Stickers', 'Moth', 300, $moths),
            self::product(15, 'Prints', 'Owl', 1200, 1),
            self::product(16, 'Cards', 'Bee', 500, 4),
        ];
    }

    private static function zone(Zone $zone, int $price, ?int $freeFrom, bool $enabled): void
    {
        self::getContainer()->get(EditShippingZoneHandler::class)(new EditShippingZone($zone, $price, $freeFrom, $enabled));
    }

    /** @param list<array{string, int}> $lines */
    private function placeOrder(array $lines, int $expectedTotal, string $country = 'FR', bool $preview = false, Locale $locale = Locale::En, ?Delivery $delivery = null): PlacedOrder
    {
        $container = self::getContainer();
        $handler = new PlaceOrderHandler(
            $container->get(CartPricer::class),
            $container->get(ProductRepository::class),
            $container->get(OrderRepository::class),
            $container->get(AtomicChange::class),
            $this->gateway,
            new FakeOrderUrls(),
            $container->get(ClockInterface::class),
        );

        return $handler(new PlaceOrder(
            array_map(static fn (array $line): CartLine => new CartLine($line[0], $line[1]), $lines),
            $country,
            $delivery ?? new Delivery('lou@example.test', 'Lou Martin', '+33 6 12 34 56 78', '1 rue des Fougères', 'Bâtiment B', '75011', 'Paris', null),
            $locale,
            $preview,
            $expectedTotal,
        ));
    }

    private function confirm(string $paymentId): void
    {
        $container = self::getContainer();
        new ConfirmPaymentHandler(
            $this->gateway,
            $container->get(OrderRepository::class),
            $container->get(ProductRepository::class),
            $container->get(AtomicChange::class),
            $this->mails,
            $container->get(ClockInterface::class),
            $this->logger,
        )(new ConfirmPayment($paymentId));
    }

    private static function order(string $token, bool $fresh = false): Order
    {
        if ($fresh) {
            self::getContainer()->get(EntityManagerInterface::class)->clear();
        }

        return self::getContainer()->get(OrderRepository::class)->withToken($token) ?? throw new \LogicException('No order '.$token);
    }

    private static function orderCount(): int
    {
        $count = self::getContainer()->get(EntityManagerInterface::class)->createQuery('SELECT COUNT(o.id) FROM '.Order::class.' o')->getSingleScalarResult();
        self::assertIsInt($count);

        return $count;
    }

    private static function available(string $slug, string $sku, bool $preview = false): int
    {
        $product = self::getContainer()->get(CatalogueQueries::class)->shopProduct($slug, $preview) ?? throw new \LogicException('No product '.$slug);
        $variant = array_find($product->variants, static fn (ShopVariantView $variant): bool => $variant->sku === $sku) ?? throw new \LogicException('No variant '.$sku);

        return $variant->available;
    }

    private static function stockOf(string $sku): ?int
    {
        foreach (self::getContainer()->get(CatalogueQueries::class)->products() as $product) {
            $variant = array_find($product->variants, static fn (VariantView $variant): bool => $variant->sku === $sku);
            if (null !== $variant) {
                return $variant->stock;
            }
        }

        return null;
    }
}
