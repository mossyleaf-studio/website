import { describe, expect, it } from 'vitest';
import { formatPrice } from './money.js';

describe('formatPrice', () => {
    it.each([
        [1250, 'fr', '12,50 €'],
        [1250, 'en', '€12.50'],
        [123456, 'fr', '1 234,56 €'],
        [123456, 'en', '€1,234.56'],
        [0, 'en', '€0.00'],
        [5, 'fr', '0,05 €'],
    ])('writes %i cents in %s as %s', (cents, locale, price) => {
        expect(formatPrice(cents, locale)).toBe(price);
    });
});
