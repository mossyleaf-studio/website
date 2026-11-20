export const chosenCountry = (stored, countries, fallback) => [stored, fallback].find((code) => countries.some((country) => country.code === code)) ?? null;

export function shippingState({ shipping, deliverable }) {
    if (shipping === null) return 'choose';
    if (!deliverable) return 'undeliverable';
    return shipping.free || shipping.price === 0 ? 'free' : 'priced';
}

export const missingForFree = (quote) => (shippingState(quote) === 'priced' && quote.shipping.freeFrom !== null ? quote.shipping.freeFrom - quote.goods : null);
