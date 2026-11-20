import { describe, expect, it } from 'vitest';
import { chosenCountry, missingForFree, shippingState } from './shipping.js';

const countries = [{ code: 'DE', name: 'Allemagne' }, { code: 'FR', name: 'France' }];
const quote = (shipping, goods = 3000, deliverable = true) => ({ goods, shipping, deliverable });
const zone = (price, freeFrom = null, free = false) => ({ zone: 'france', price, freeFrom, free });

describe('chosenCountry', () => {
    it('keeps the stored country when it can be delivered', () => {
        expect(chosenCountry('DE', countries, 'FR')).toBe('DE');
    });

    it('falls back to the default country', () => {
        expect(chosenCountry(null, countries, 'FR')).toBe('FR');
        expect(chosenCountry('US', countries, 'FR')).toBe('FR');
    });

    it('chooses none when the default cannot be delivered either', () => {
        expect(chosenCountry(null, [{ code: 'DE', name: 'Allemagne' }], 'FR')).toBeNull();
        expect(chosenCountry('FR', [], 'FR')).toBeNull();
    });
});

describe('shippingState', () => {
    it('asks for a country before any price', () => {
        expect(shippingState(quote(null, 3000, false))).toBe('choose');
    });

    it('says when the country cannot be delivered', () => {
        expect(shippingState(quote(zone(500), 3000, false))).toBe('undeliverable');
    });

    it('is free from the threshold or when the zone costs nothing', () => {
        expect(shippingState(quote(zone(0, 2000, true)))).toBe('free');
        expect(shippingState(quote(zone(0)))).toBe('free');
    });

    it('is priced otherwise', () => {
        expect(shippingState(quote(zone(500, 6000)))).toBe('priced');
    });
});

describe('missingForFree', () => {
    it('tells how much is missing to reach the threshold', () => {
        expect(missingForFree(quote(zone(500, 6000), 4550))).toBe(1450);
    });

    it('is null without a threshold, once it is reached, or without a deliverable country', () => {
        expect(missingForFree(quote(zone(500), 4550))).toBeNull();
        expect(missingForFree(quote(zone(0, 4000, true), 4550))).toBeNull();
        expect(missingForFree(quote(null, 4550, false))).toBeNull();
        expect(missingForFree(quote(zone(500, 6000), 4550, false))).toBeNull();
    });
});
