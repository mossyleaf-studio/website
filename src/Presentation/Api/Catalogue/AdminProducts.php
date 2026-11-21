<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\ProductView;
use App\Domain\Shared\Locale;
use App\Presentation\Web\Shop\ShopUrls;

final readonly class AdminProducts
{
    public function __construct(private ShopUrls $urls)
    {
    }

    /** @return array<mixed> */
    public function one(ProductView $product): array
    {
        return [...get_object_vars($product), 'preview' => $this->preview($product)];
    }

    /**
     * @param list<ProductView> $products
     *
     * @return list<array<mixed>>
     */
    public function all(array $products): array
    {
        return array_map($this->one(...), $products);
    }

    /** @return array<string, string>|null */
    private function preview(ProductView $product): ?array
    {
        if ($product->retired || $product->price <= 0) {
            return null;
        }

        $preview = [];
        foreach (Locale::cases() as $locale) {
            $preview[$locale->value] = $this->urls->to('shop_product', $locale, true, ['slug' => $product->slug]);
        }

        return $preview;
    }
}
