import { describe, expect, it } from 'vitest';
import { canShift, collectionLabel, completeness, filterProducts, pageFromQuery, pageOf, pickSkip, pickState, placePage, shift, totalStock, unpickSkip } from './products.js';

const product = (overrides = {}) => ({
    id: 'p1',
    reference: 'PRINT-01',
    name: 'Fox print',
    displayName: 'Fox print A5',
    category: { id: 'c1', name: 'Prints' },
    variants: [{ sku: 'PRINT-01-A5', name: 'A5', stock: 3 }, { sku: 'PRINT-01-A4', name: 'A4', stock: 2 }],
    title: 'Fox',
    titleFr: 'Renard',
    description: '',
    descriptionFr: '',
    photos: [{ id: 'a' }],
    ...overrides,
});

describe('completeness', () => {
    it('is empty for a finished product', () => {
        expect(completeness(product())).toEqual([]);
    });

    it('flags a product without photo', () => {
        expect(completeness(product({ photos: [] }))).toEqual(['noPhoto']);
    });

    it('flags a missing French title', () => {
        expect(completeness(product({ titleFr: '  ' }))).toEqual(['frenchMissing']);
    });

    it('flags an English description left untranslated', () => {
        expect(completeness(product({ description: 'Hand-drawn', descriptionFr: '' }))).toEqual(['frenchMissing']);
    });

    it('does not ask for a French description when there is none', () => {
        expect(completeness(product({ description: '', descriptionFr: '' }))).toEqual([]);
    });

    it('lists both gaps, photo first', () => {
        expect(completeness(product({ photos: [], titleFr: '' }))).toEqual(['noPhoto', 'frenchMissing']);
    });
});

describe('totalStock', () => {
    it('adds the stock of the variants', () => {
        expect(totalStock(product())).toBe(5);
    });

    it('is zero without variants', () => {
        expect(totalStock(product({ variants: [] }))).toBe(0);
    });
});

describe('filterProducts', () => {
    const fox = product();
    const owl = product({ id: 'p2', reference: 'CARD-02', name: 'Chouette', displayName: 'Carte chouette', title: '', titleFr: 'Éclat', category: { id: 'c2', name: 'Cards' }, variants: [{ sku: 'CARD-02-STD', name: null, stock: 0 }] });
    const all = [fox, owl];

    it('keeps everything without query or category', () => {
        expect(filterProducts(all, { query: '', category: '' })).toEqual(all);
    });

    it.each([['fox'], ['FOX print'], ['card-02'], ['std'], ['eclat'], ['chouette']])('finds by %s', (query) => {
        expect(filterProducts(all, { query, category: '' }).length).toBe(1);
    });

    it('finds nothing for an unknown word', () => {
        expect(filterProducts(all, { query: 'zebra', category: '' })).toEqual([]);
    });

    it('filters by category id', () => {
        expect(filterProducts(all, { query: '', category: 'c2' })).toEqual([owl]);
    });

    it('combines both', () => {
        expect(filterProducts(all, { query: 'fox', category: 'c2' })).toEqual([]);
    });
});

describe('pickSkip', () => {
    it('skips retired products first, then the ones already picked', () => {
        expect(pickSkip(product({ retired: true, picked: false }))).toBe('retired');
        expect(pickSkip(product({ retired: true, picked: true }))).toBe('retired');
        expect(pickSkip(product({ retired: false, picked: true }))).toBe('alreadyPicked');
        expect(pickSkip(product({ retired: false, picked: false }))).toBeNull();
    });
});

describe('unpickSkip', () => {
    it('skips only the products that are not picked, retired or not', () => {
        expect(unpickSkip(product({ picked: false }))).toBe('notPicked');
        expect(unpickSkip(product({ picked: true, retired: true }))).toBeNull();
    });
});

describe('pickState', () => {
    it('says whether the product is picked and warns when a picked product is hidden', () => {
        expect(pickState(product({ picked: false, visible: true }))).toBe('off');
        expect(pickState(product({ picked: false, visible: false }))).toBe('off');
        expect(pickState(product({ picked: true, visible: true }))).toBe('on');
        expect(pickState(product({ picked: true, visible: false }))).toBe('hidden');
    });
});

describe('collection filter', () => {
    const fox = product({ id: 'a', collection: { id: 'k1', name: 'Forest', label: 'Woods', labelFr: 'Bois' } });
    const bare = product({ id: 'b', collection: null });
    const list = [fox, bare];

    it('keeps everything without a collection filter', () => {
        expect(filterProducts(list, { query: '', category: '', collection: '' })).toEqual(list);
    });

    it('keeps the products of one collection', () => {
        expect(filterProducts(list, { query: '', category: '', collection: 'k1' })).toEqual([fox]);
    });

    it('keeps the products without collection', () => {
        expect(filterProducts(list, { query: '', category: '', collection: 'none' })).toEqual([bare]);
    });
});

describe('collectionLabel', () => {
    const collection = { name: 'Forest', label: 'Woods', labelFr: 'Bois' };

    it('uses the label of the admin language', () => {
        expect(collectionLabel(collection, 'fr')).toBe('Bois');
        expect(collectionLabel(collection, 'en')).toBe('Woods');
    });

    it('falls back on the other language, then the mossytrunk name', () => {
        expect(collectionLabel({ ...collection, labelFr: '' }, 'fr')).toBe('Woods');
        expect(collectionLabel({ ...collection, label: '', labelFr: '' }, 'en')).toBe('Forest');
    });
});

const ids = (count) => Array.from({ length: count }, (_, index) => ({ id: `p${index + 1}` }));
const names = (list) => list.map((item) => item.id);

describe('pageOf', () => {
    it('numbers the pages from 1', () => {
        expect([0, 19, 20, 39, 40].map((index) => pageOf(index, 20))).toEqual([1, 1, 2, 2, 3]);
    });
});

describe('canShift', () => {
    it('refuses to leave the whole list, whatever the page', () => {
        expect(canShift(0, -1, 45)).toBe(false);
        expect(canShift(44, 1, 45)).toBe(false);
    });

    it('allows every other move, across a page boundary too', () => {
        expect(canShift(0, 1, 45)).toBe(true);
        expect(canShift(20, -1, 45)).toBe(true);
        expect(canShift(19, 1, 45)).toBe(true);
        expect(canShift(44, -1, 45)).toBe(true);
    });

    it('refuses everything for a list of one', () => {
        expect(canShift(0, -1, 1)).toBe(false);
        expect(canShift(0, 1, 1)).toBe(false);
    });
});

describe('shift', () => {
    it('moves one place inside a page', () => {
        expect(names(shift(ids(5), 1, 1))).toEqual(['p1', 'p3', 'p2', 'p4', 'p5']);
        expect(names(shift(ids(5), 3, -1))).toEqual(['p1', 'p2', 'p4', 'p3', 'p5']);
    });

    it('moves the last row of a page onto the next one', () => {
        const moved = shift(ids(45), 19, 1);
        expect(moved[20].id).toBe('p20');
        expect(moved[19].id).toBe('p21');
        expect(pageOf(20, 20)).toBe(2);
    });

    it('moves the first row of page 2 up onto page 1', () => {
        const moved = shift(ids(45), 20, -1);
        expect(moved[19].id).toBe('p21');
        expect(moved[20].id).toBe('p20');
    });

    it('leaves the ends alone and does not touch its input', () => {
        const list = ids(3);
        expect(names(shift(list, 0, -1))).toEqual(['p1', 'p2', 'p3']);
        expect(names(shift(list, 2, 1))).toEqual(['p1', 'p2', 'p3']);
        expect(names(list)).toEqual(['p1', 'p2', 'p3']);
    });
});

describe('placePage', () => {
    it('puts the page back at its place in the whole list after a drag on page 2 of 3', () => {
        const list = ids(45);
        const page = list.slice(20, 40);
        const dragged = [page[1], page[0], ...page.slice(2)];
        const placed = placePage(list, 2, 20, dragged);
        expect(names(placed.slice(0, 20))).toEqual(names(list.slice(0, 20)));
        expect(names(placed.slice(20, 22))).toEqual(['p22', 'p21']);
        expect(names(placed.slice(40))).toEqual(names(list.slice(40)));
        expect(placed).toHaveLength(45);
    });

    it('handles a short last page', () => {
        const list = ids(45);
        const placed = placePage(list, 3, 20, [list[44], ...list.slice(40, 44)]);
        expect(names(placed.slice(40))).toEqual(['p45', 'p41', 'p42', 'p43', 'p44']);
    });

    it('handles a page of one row', () => {
        const list = ids(21);
        expect(names(placePage(list, 2, 20, [list[20]]))).toEqual(names(list));
    });
});

describe('pageFromQuery', () => {
    it('reads a page number and falls back to 1', () => {
        expect(pageFromQuery('3')).toBe(3);
        expect(pageFromQuery(undefined)).toBe(1);
        expect(pageFromQuery('abc')).toBe(1);
        expect(pageFromQuery('0')).toBe(1);
        expect(pageFromQuery('-2')).toBe(1);
        expect(pageFromQuery(['4', '5'])).toBe(4);
    });
});
