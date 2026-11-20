<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Sales\ShippingZoneQueries;
use App\Domain\Sales\Country;
use App\Domain\Sales\Zone;
use App\Domain\Shared\Locale;
use Symfony\Component\Intl\Countries;

final readonly class CartCountries
{
    public function __construct(private ShippingZoneQueries $zones)
    {
    }

    /** @return list<array{code: string, name: string}> */
    public function deliverable(Locale $locale): array
    {
        $zones = $this->zones->enabled();
        $countries = [];
        foreach (Countries::getNames($locale->value) as $code => $name) {
            if (\in_array(Zone::of(Country::of($code)), $zones, true)) {
                $countries[] = ['code' => $code, 'name' => $name];
            }
        }

        return $countries;
    }
}
