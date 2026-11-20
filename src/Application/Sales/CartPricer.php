<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Catalogue\ShopProductView;
use App\Application\Catalogue\ShopVariantView;
use App\Domain\Sales\Country;
use App\Domain\Sales\Exception\CartTooLarge;
use App\Domain\Sales\ShippingZoneRepository;
use App\Domain\Sales\Zone;
use App\Domain\Shared\Locale;

final readonly class CartPricer
{
    public const int MAX_LINES = 30;
    public const int MAX_QUANTITY = 10;

    public function __construct(
        private CatalogueQueries $catalogue,
        private ShippingZoneRepository $zones,
    ) {
    }

    /** @param list<CartLine> $lines */
    public function quote(array $lines, ?string $country, bool $preview, Locale $locale): Quote
    {
        $wanted = self::merge($lines);
        if (\count($wanted) > self::MAX_LINES) {
            throw new CartTooLarge(self::MAX_LINES);
        }
        $zone = null === $country ? null : $this->zones->get(Zone::of(Country::of($country)));
        $offers = $this->offers($preview);

        $kept = [];
        $issues = [];
        foreach ($wanted as $sku => $quantity) {
            $sku = (string) $sku;
            [$product, $variant] = $offers[$sku] ?? [null, null];
            if (null === $product || null === $variant || 0 === $variant->available) {
                $issues[] = QuoteIssue::removed($sku, $product?->title->in($locale));
                continue;
            }

            $title = $product->title->in($locale);
            $allowed = min($quantity, $variant->available, self::MAX_QUANTITY);
            if ($allowed < $quantity) {
                $issues[] = QuoteIssue::reduced($sku, $title);
            }
            $kept[] = new QuoteLine($sku, $product->displayName, $title, $variant->name, $product->price, $allowed, $product->price * $allowed, $product->slug, $product->photos[0] ?? null, $variant->available);
        }

        $goods = array_sum(array_map(static fn (QuoteLine $line): int => $line->lineTotal, $kept));
        $units = array_sum(array_map(static fn (QuoteLine $line): int => $line->quantity, $kept));
        $shipping = null === $zone ? null : ShippingQuote::of($zone, $goods, $units);
        $deliverable = null !== $zone && $zone->isEnabled();

        return new Quote($kept, $issues, $goods, $shipping, $deliverable, $goods + ($deliverable && null !== $shipping ? $shipping->price : 0), $units);
    }

    /**
     * @param list<CartLine> $lines
     *
     * @return array<string, int>
     */
    private static function merge(array $lines): array
    {
        $wanted = [];
        foreach ($lines as $line) {
            $wanted[$line->sku] = self::bounded(($wanted[$line->sku] ?? 0) + self::bounded($line->quantity));
        }

        return $wanted;
    }

    private static function bounded(int $quantity): int
    {
        return max(1, min($quantity, self::MAX_QUANTITY + 1));
    }

    /** @return array<string, array{ShopProductView, ShopVariantView}> */
    private function offers(bool $preview): array
    {
        $offers = [];
        foreach ($this->catalogue->shop($preview) as $product) {
            foreach ($product->variants as $variant) {
                $offers[$variant->sku] = [$product, $variant];
            }
        }

        return $offers;
    }
}
