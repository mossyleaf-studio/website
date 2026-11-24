<?php

declare(strict_types=1);

namespace App\Tests\Functional\Sales;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Catalogue\ShopVariantView;
use App\Application\Sales\PaymentState;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CataloguePushCountsSalesTest extends WebTestCase
{
    use SellsOrders;

    protected function setUp(): void
    {
        $this->doubles();
    }

    public function testWithoutOrdersThePushWritesTheStockItIsSent(): void
    {
        self::stockTheShop();

        self::push(self::shopProducts(7));

        self::assertSame([7, 12, 1], [self::stockOf('STI-MOTH'), self::stockOf('PRI-FOX-A4'), self::stockOf('PRI-OWL')]);
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function ordersThatHoldSoldUnits(): iterable
    {
        yield 'a shipped order' => [false, true];
        yield 'a paid test order' => [true, false];
    }

    #[DataProvider('ordersThatHoldSoldUnits')]
    public function testThePushSubtractsTheUnitsOfAnOrderNeverExported(bool $test, bool $shipped): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(8));
        $placed = $this->paidOrder([['STI-MOTH', 2]], 1250, $test);
        if ($shipped) {
            self::ship($placed);
        }

        self::push(self::shopProducts(8));

        self::assertSame(6, self::stockOf('STI-MOTH'));
        self::assertSame(6, self::available('stickers-moth', 'STI-MOTH'));
        self::assertSame(12, self::stockOf('PRI-FOX-A4'));
    }

    /** @return iterable<string, array{string, int}> */
    public static function ordersThatAreNotSubtracted(): iterable
    {
        yield 'an exported order' => ['exported', 8];
        yield 'a cancelled order' => ['cancelled', 8];
        yield 'a pending order' => ['pending', 6];
        yield 'a failed order' => ['failed', 8];
        yield 'an expired order' => ['expired', 8];
    }

    #[DataProvider('ordersThatAreNotSubtracted')]
    public function testThePushSubtractsNoOtherOrder(string $case, int $available): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(8));
        if (\in_array($case, ['exported', 'cancelled'], true)) {
            $placed = $this->paidOrder([['STI-MOTH', 2]], 1250);
            if ('exported' === $case) {
                self::export();
            } else {
                self::cancel($placed);
            }
        } else {
            $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
            if ('pending' !== $case) {
                $this->settleAs($placed, 'failed' === $case ? PaymentState::Failed : PaymentState::Expired);
            }
        }

        self::push(self::shopProducts(8));

        self::assertSame(8, self::stockOf('STI-MOTH'));
        self::assertSame($available, self::available('stickers-moth', 'STI-MOTH'));
    }

    public function testTheUnitsOfSeveralOrdersOnOneSkuAddUp(): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(8));
        $this->paidOrder([['STI-MOTH', 2]], 1250);
        $this->paidOrder([['STI-MOTH', 3]], 1550);
        $this->paidOrder([['STI-MOTH', 1], ['PRI-FOX-A4', 1]], 2450);

        self::push(self::shopProducts(8));

        self::assertSame([2, 11, 1], [self::stockOf('STI-MOTH'), self::stockOf('PRI-FOX-A4'), self::stockOf('PRI-OWL')]);
    }

    public function testThePushNeverWritesAStockBelowZero(): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(8));
        $this->paidOrder([['STI-MOTH', 5]], 2150);

        self::push(self::shopProducts(3));

        self::assertSame(0, self::stockOf('STI-MOTH'));
        self::assertSame('soldOut', self::variant('stickers-moth', 'STI-MOTH')->state);
    }

    public function testASoldSkuThatIsNoLongerPushedChangesNothing(): void
    {
        self::stockTheShop();
        $this->paidOrder([['PRI-FOX-A4', 1]], 2150);

        self::push(self::shopProducts(8, foxA4: false));

        self::assertNull(self::stockOf('PRI-FOX-A4'));
        self::assertSame([8, 0, 1], [self::stockOf('STI-MOTH'), self::stockOf('PRI-FOX-A5'), self::stockOf('PRI-OWL')]);
    }

    public function testTheStockFollowsTheSalesAsMossytrunkReadsThemAndPushes(): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(8));
        $this->paidOrder([['STI-MOTH', 2]], 1250);
        self::assertSame(6, self::available('stickers-moth', 'STI-MOTH'));

        self::export();
        self::push(self::shopProducts(6));

        self::assertSame([6, 6], [self::stockOf('STI-MOTH'), self::available('stickers-moth', 'STI-MOTH')]);

        $this->paidOrder([['STI-MOTH', 1]], 950);
        self::push(self::shopProducts(6));

        self::assertSame([5, 5], [self::stockOf('STI-MOTH'), self::available('stickers-moth', 'STI-MOTH')]);

        self::export();
        self::push(self::shopProducts(5));

        self::assertSame([5, 5], [self::stockOf('STI-MOTH'), self::available('stickers-moth', 'STI-MOTH')]);
        self::assertSame('inStock', self::variant('stickers-moth', 'STI-MOTH')->state);
    }

    public function testAPaidOrderCancelledBeforeItsExportGivesItsUnitsBackAndThePushWritesTheStockItIsSent(): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(8));
        $placed = $this->paidOrder([['STI-MOTH', 2]], 1250);
        self::assertSame(6, self::stockOf('STI-MOTH'));

        self::cancel($placed);

        self::assertSame(8, self::stockOf('STI-MOTH'));
        self::push(self::shopProducts(7));
        self::assertSame([7, 7], [self::stockOf('STI-MOTH'), self::available('stickers-moth', 'STI-MOTH')]);
    }

    public function testAnOrderThePushCouldSubtractInFullGivesItsWholeQuantityBack(): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(8));
        $placed = $this->paidOrder([['STI-MOTH', 2]], 1250);
        self::push(self::shopProducts(8));
        self::assertSame(6, self::stockOf('STI-MOTH'));

        self::cancel($placed);

        self::assertSame(8, self::stockOf('STI-MOTH'));
    }

    public function testAnOrderThePushCouldNotSubtractGivesNothingBack(): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(8));
        $this->paidOrder([['STI-MOTH', 2]], 1250);
        $newer = $this->paidOrder([['STI-MOTH', 2]], 1250);
        self::push(self::shopProducts(2));
        self::assertSame(0, self::stockOf('STI-MOTH'));

        self::cancel($newer);

        self::assertSame(0, self::stockOf('STI-MOTH'));
    }

    public function testThePushedUnitsServeTheOlderOrdersFirst(): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(8));
        $older = $this->paidOrder([['STI-MOTH', 1]], 950);
        $newer = $this->paidOrder([['STI-MOTH', 1]], 950);
        self::push(self::shopProducts(1));
        self::assertSame(0, self::stockOf('STI-MOTH'));

        self::cancel($newer);

        self::assertSame(0, self::stockOf('STI-MOTH'));

        self::cancel($older);

        self::assertSame(1, self::stockOf('STI-MOTH'));
    }

    public function testADeletedPaidTestOrderGivesBackWhatThePushLeftIt(): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(8));
        $placed = $this->paidOrder([['STI-MOTH', 2]], 1250, test: true);
        self::push(self::shopProducts(1));
        self::assertSame(0, self::stockOf('STI-MOTH'));

        self::deleteTestOrder($placed);

        self::assertSame(1, self::stockOf('STI-MOTH'));
    }

    public function testADeletedPaidTestOrderGivesItsUnitsBackAndIsNoLongerSubtracted(): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(8));
        $placed = $this->paidOrder([['STI-MOTH', 2]], 1250, test: true);
        self::push(self::shopProducts(8));
        self::assertSame(6, self::stockOf('STI-MOTH'));

        self::deleteTestOrder($placed);

        self::assertSame(8, self::stockOf('STI-MOTH'));
        self::push(self::shopProducts(7));
        self::assertSame([7, 7], [self::stockOf('STI-MOTH'), self::available('stickers-moth', 'STI-MOTH')]);
    }

    public function testAKeptPaidTestOrderIsSubtractedByEveryPush(): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(8));
        $this->paidOrder([['STI-MOTH', 2]], 1250, test: true);

        self::push(self::shopProducts(8));
        self::assertSame(6, self::stockOf('STI-MOTH'));
        self::assertSame([], self::export());
        self::push(self::shopProducts(8));
        self::assertSame(6, self::stockOf('STI-MOTH'));
        self::push(self::shopProducts(5));
        self::assertSame([3, 3], [self::stockOf('STI-MOTH'), self::available('stickers-moth', 'STI-MOTH')]);
    }

    private static function variant(string $slug, string $sku): ShopVariantView
    {
        $product = self::getContainer()->get(CatalogueQueries::class)->shopProduct($slug, false) ?? throw new \LogicException('No product '.$slug);

        return array_find($product->variants, static fn (ShopVariantView $variant): bool => $variant->sku === $sku) ?? throw new \LogicException('No variant '.$sku);
    }
}
