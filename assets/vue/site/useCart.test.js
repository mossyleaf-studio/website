import { effectScope } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CART_KEY } from './cart.js';
import { useCart } from './useCart.js';

let store;

beforeEach(() => {
    store = new Map();
    vi.stubGlobal('window', {
        localStorage: {
            getItem: (key) => (store.has(key) ? store.get(key) : null),
            setItem: (key, value) => store.set(key, String(value)),
            removeItem: (key) => store.delete(key),
        },
        addEventListener: () => {},
        removeEventListener: () => {},
    });
});

afterEach(() => vi.unstubAllGlobals());

const mount = () => effectScope().run(() => useCart());
const stored = () => JSON.parse(store.get(CART_KEY));

describe('useCart', () => {
    it('keeps the cart in the storage', () => {
        const cart = mount();

        expect(cart.add({ sku: 'A', quantity: 2 })).toBe(true);

        expect(stored().lines).toEqual([{ sku: 'A', quantity: 2 }]);
        expect(cart.units.value).toBe(2);
        expect(mount().units.value).toBe(2);
    });

    it('writes nothing when the cart does not change', () => {
        const cart = mount();
        cart.add({ sku: 'A', quantity: 1 });

        expect(cart.setQuantity('A', 1)).toBe(false);
        expect(cart.remove('Z')).toBe(false);
    });

    it('empties the cart once when the order that was placed is paid, keeping the country', () => {
        const cart = mount();
        cart.add({ sku: 'A', quantity: 2 });
        cart.setCountry('FR');
        cart.placed('ML-AAAA-BBBB');

        expect(cart.paid('ML-AAAA-BBBB')).toBe(true);
        expect(stored()).toEqual({ lines: [], country: 'FR' });

        cart.add({ sku: 'B', quantity: 1 });

        expect(cart.paid('ML-AAAA-BBBB')).toBe(false);
        expect(stored().lines).toEqual([{ sku: 'B', quantity: 1 }]);
    });

    it('leaves the cart alone for an order that was not the one placed from this browser', () => {
        const cart = mount();
        cart.add({ sku: 'A', quantity: 1 });

        expect(cart.paid('ML-AAAA-BBBB')).toBe(false);

        cart.placed('ML-AAAA-BBBB');

        expect(cart.paid('ML-CCCC-DDDD')).toBe(false);
        expect(stored().lines).toEqual([{ sku: 'A', quantity: 1 }]);
    });

    it('starts empty when the storage cannot be read', () => {
        window.localStorage.getItem = () => {
            throw new Error('blocked');
        };

        expect(mount().units.value).toBe(0);
    });
});
