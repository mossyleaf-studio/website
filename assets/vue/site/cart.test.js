import { describe, expect, it } from 'vitest';
import { addLine, cartUnits, emptyCart, issueNames, pricedLines, readCart, removeLine, setCountry, setQuantity, settle } from './cart.js';

const cart = (lines, country = null) => ({ lines: lines.map(([sku, quantity]) => ({ sku, quantity })), country });
const priced = (sku, quantity, unitPrice = 1000, available = 10) => ({ sku, title: `Title ${sku}`, variant: null, unitPrice, quantity, lineTotal: unitPrice * quantity, image: null, url: `/fr/shop/${sku}`, available });

describe('the stored cart', () => {
    it('starts empty', () => {
        expect(emptyCart()).toEqual({ lines: [], country: null });
        expect(readCart(null)).toEqual(emptyCart());
    });

    it('reads what was stored', () => {
        expect(readCart(JSON.stringify(cart([['A', 2], ['B', 1]], 'FR')))).toEqual(cart([['A', 2], ['B', 1]], 'FR'));
    });

    it.each(['not json', '42', 'null', '"cart"', '[]', '{"lines":"A"}', '{"lines":{"sku":"A"}}'])('reads %s as an empty cart', (stored) => {
        expect(readCart(stored)).toEqual(emptyCart());
    });

    it('drops the lines it cannot read and keeps the others', () => {
        const stored = { lines: [null, 'A', { sku: '' }, { sku: 3, quantity: 1 }, { sku: 'B', quantity: 'two' }, { sku: 'x'.repeat(321), quantity: 1 }, { sku: 'C', quantity: 2 }], country: 'FR' };

        expect(readCart(JSON.stringify(stored))).toEqual(cart([['C', 2]], 'FR'));
    });

    it('merges a sku stored twice and keeps every quantity between 1 and 10', () => {
        const stored = { lines: [{ sku: 'A', quantity: 7 }, { sku: 'B', quantity: -3 }, { sku: 'A', quantity: 6 }, { sku: 'C', quantity: 2.7 }], country: null };

        expect(readCart(JSON.stringify(stored))).toEqual(cart([['A', 10], ['B', 1], ['C', 2]]));
    });

    it('keeps at most 30 lines', () => {
        const stored = { lines: Array.from({ length: 40 }, (_, index) => ({ sku: `S${index}`, quantity: 1 })), country: null };

        expect(readCart(JSON.stringify(stored)).lines).toHaveLength(30);
    });

    it.each([['fr'], ['FRA'], [12], [''], [{}]])('forgets the country %j', (country) => {
        expect(readCart(JSON.stringify({ lines: [], country })).country).toBeNull();
    });
});

describe('changing the cart', () => {
    it('adds a new line at the end', () => {
        expect(addLine(cart([['A', 1]]), { sku: 'B', quantity: 3 })).toEqual(cart([['A', 1], ['B', 3]]));
    });

    it('adds to the line of a sku already in the cart, up to 10', () => {
        expect(addLine(cart([['A', 4]]), { sku: 'A', quantity: 3 })).toEqual(cart([['A', 7]]));
        expect(addLine(cart([['A', 8]]), { sku: 'A', quantity: 5 })).toEqual(cart([['A', 10]]));
    });

    it('adds no more than the stock left', () => {
        expect(addLine(cart([['A', 2]]), { sku: 'A', quantity: 3 }, 3)).toEqual(cart([['A', 3]]));
        expect(addLine(emptyCart(), { sku: 'A', quantity: 5 }, 2)).toEqual(cart([['A', 2]]));
    });

    it('adds nothing once the line holds the stock left', () => {
        const held = cart([['A', 3]]);

        expect(addLine(held, { sku: 'A', quantity: 1 }, 3)).toEqual(held);
        expect(addLine(cart([['A', 5]]), { sku: 'A', quantity: 1 }, 3)).toEqual(cart([['A', 5]]));
        expect(addLine(emptyCart(), { sku: 'A', quantity: 1 }, 0)).toEqual(emptyCart());
    });

    it('adds at least one', () => {
        expect(addLine(emptyCart(), { sku: 'A', quantity: 0 })).toEqual(cart([['A', 1]]));
    });

    it('adds no 31st line but still adds to an existing one', () => {
        const full = cart(Array.from({ length: 30 }, (_, index) => [`S${index}`, 1]));

        expect(addLine(full, { sku: 'NEW', quantity: 1 })).toBe(full);
        expect(addLine(full, { sku: 'S0', quantity: 1 }).lines[0]).toEqual({ sku: 'S0', quantity: 2 });
    });

    it('sets a quantity within 1 and 10', () => {
        expect(setQuantity(cart([['A', 1], ['B', 1]]), 'B', 4)).toEqual(cart([['A', 1], ['B', 4]]));
        expect(setQuantity(cart([['A', 1]]), 'A', 40)).toEqual(cart([['A', 10]]));
        expect(setQuantity(cart([['A', 3]]), 'A', 0)).toEqual(cart([['A', 1]]));
    });

    it('leaves the cart alone for a quantity of a sku it does not hold', () => {
        expect(setQuantity(cart([['A', 1]]), 'B', 4)).toEqual(cart([['A', 1]]));
    });

    it('removes a line', () => {
        expect(removeLine(cart([['A', 1], ['B', 2]], 'FR'), 'A')).toEqual(cart([['B', 2]], 'FR'));
    });

    it('remembers the country', () => {
        expect(setCountry(cart([['A', 1]]), 'DE')).toEqual(cart([['A', 1]], 'DE'));
    });

    it('counts the units', () => {
        expect(cartUnits(cart([['A', 2], ['B', 3]]))).toBe(5);
        expect(cartUnits(emptyCart())).toBe(0);
    });
});

describe('applying a quote', () => {
    it('keeps the quantities and the lines the server priced, in its order, and the country', () => {
        const quote = { lines: [priced('A', 2), priced('C', 1)], issues: [{ type: 'reduced', sku: 'A', title: 'Title A' }, { type: 'removed', sku: 'B', title: null }] };

        expect(settle(cart([['A', 9], ['B', 1], ['C', 1]], 'FR'), quote)).toEqual(cart([['A', 2], ['C', 1]], 'FR'));
    });

    it('empties the cart when nothing could be priced', () => {
        expect(settle(cart([['B', 1]], 'FR'), { lines: [], issues: [{ type: 'removed', sku: 'B', title: null }] })).toEqual(cart([], 'FR'));
    });

    it('shows the lines of the cart with what the server knows of them', () => {
        const quote = { lines: [priced('A', 2, 1500), priced('B', 1, 400)], issues: [] };

        expect(pricedLines(cart([['B', 3], ['A', 2], ['Z', 1]]), quote)).toEqual([
            { ...priced('B', 3, 400) },
            { ...priced('A', 2, 1500) },
        ]);
    });

    it('names the products of each issue and counts those without a name', () => {
        const issues = [
            { type: 'removed', sku: 'A', title: 'Fern print' },
            { type: 'reduced', sku: 'B', title: 'Moss print' },
            { type: 'removed', sku: 'C', title: null },
            { type: 'removed', sku: 'D', title: null },
            { type: 'removed', sku: 'E', title: 'Fern print' },
        ];

        expect(issueNames(issues, 'removed')).toEqual({ titles: ['Fern print'], unnamed: 2 });
        expect(issueNames(issues, 'reduced')).toEqual({ titles: ['Moss print'], unnamed: 0 });
        expect(issueNames([], 'reduced')).toEqual({ titles: [], unnamed: 0 });
    });
});
