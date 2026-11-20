<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Application\Catalogue\IntegrationToken;
use App\Application\Contact\StudioMailbox;
use App\Application\Sales\PaymentGateway;
use App\Application\Sales\ShippingZoneQueries;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductRepository;
use App\Domain\Content\LegalKind;
use App\Domain\Content\LegalPage;
use App\Domain\Content\LegalPageRepository;
use App\Domain\Content\SiteTextRepository;
use App\Domain\Sales\OrderRepository;
use App\Domain\Shared\Locale;
use Psr\Clock\ClockInterface;

final readonly class ShopReadiness
{
    private const array LEGAL = ['terms' => LegalKind::Terms, 'legalNotice' => LegalKind::LegalNotice, 'privacy' => LegalKind::Privacy, 'shippingReturns' => LegalKind::ShippingReturns];

    public function __construct(
        private SiteTextRepository $texts,
        private PaymentGateway $gateway,
        private StudioMailbox $mailbox,
        private ShippingZoneQueries $zones,
        private ProductRepository $products,
        private LegalPageRepository $legalPages,
        private IntegrationToken $mossytrunk,
        private OrderRepository $orders,
        private ClockInterface $clock,
    ) {
    }

    public function check(): ReadinessView
    {
        $onSale = $this->products->forSale(false);
        $written = [];
        foreach ($this->legalPages->all() as $page) {
            $written[$page->kind()->value] = self::hasText($page);
        }
        $legal = [];
        foreach (self::LEGAL as $key => $kind) {
            $legal[] = ReadinessItem::unless($key, !($written[$kind->value] ?? false), LegalKind::ShippingReturns === $kind ? ReadinessState::Warning : ReadinessState::Blocker);
        }
        $withoutPhoto = \count(array_filter($onSale, static fn (Product $product): bool => [] === $product->photos()));
        $testOrders = $this->orders->testOrdersHoldingUnits($this->clock->now());

        return new ReadinessView([
            ReadinessItem::unless('identity', '' === trim($this->texts->current()->studioName()), ReadinessState::Blocker),
            ReadinessItem::unless('payment', !$this->gateway->isReady(), ReadinessState::Blocker),
            ReadinessItem::unless('mail', !$this->mailbox->isOpen() || !$this->mailbox->hasTransport(), ReadinessState::Blocker),
            ReadinessItem::unless('shipping', [] === $this->zones->enabled(), ReadinessState::Blocker),
            ReadinessItem::unless('products', [] === $onSale, ReadinessState::Blocker, \count($onSale)),
            ...$legal,
            ReadinessItem::unless('photos', $withoutPhoto > 0, ReadinessState::Warning, $withoutPhoto),
            ReadinessItem::unless('mossytrunk', !$this->mossytrunk->isUsable(), ReadinessState::Warning),
            ReadinessItem::unless('testOrders', $testOrders > 0, ReadinessState::Warning, $testOrders),
        ]);
    }

    private static function hasText(LegalPage $page): bool
    {
        return '' !== trim($page->text()->in(Locale::En));
    }
}
