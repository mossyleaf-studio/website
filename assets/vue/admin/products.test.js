import { describe, expect, it } from 'vitest';
import { collectionLabel, completeness, filterProducts, pickSkip, pickState, totalStock, unpickSkip } from './products.js';

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
