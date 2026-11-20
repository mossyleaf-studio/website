import { describe, expect, it } from 'vitest';
import { boundedQuantity, preselectedVariant } from './buying.js';

const variant = (sku, available) => ({ sku, name: sku, available, soldOut: available === 0 });

describe('preselectedVariant', () => {
    it('picks the first variant that can be bought', () => {
        expect(preselectedVariant([variant('A3', 0), variant('A4', 2), variant('A5', 7)]).sku).toBe('A4');
    });

    it('picks none when every one is sold out', () => {
        expect(preselectedVariant([variant('A3', 0), variant('A4', 0)])).toBeNull();
    });

    it('has nothing to pick without variants', () => {
        expect(preselectedVariant([])).toBeNull();
    });
});

describe('boundedQuantity', () => {
    it.each([
        [3, 5, 3],
        [0, 5, 1],
        [-2, 5, 1],
        [9, 5, 5],
        [2.6, 5, 2],
        [Number.NaN, 5, 1],
        [4, 0, 1],
    ])('keeps %s within 1 and %i as %i', (value, available, quantity) => {
        expect(boundedQuantity(value, available)).toBe(quantity);
    });
});
