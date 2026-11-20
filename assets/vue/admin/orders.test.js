import { describe, expect, it } from 'vitest';
import { addressLines, actionsFor, countryName, ordersOfTab, searchOrders, statusTone, tabCounts, tabOf } from './orders.js';

const order = (overrides = {}) => ({
    id: 'o1',
    reference: 'ML-AB12-CD34',
    status: 'paid',
    test: false,
    name: 'Éloïse Martin',
    email: 'eloise@example.test',
    line1: '12 rue des Lilas',
    line2: null,
    postalCode: '75011',
    city: 'Paris',
    region: null,
    country: 'FR',
    hasCheckout: true,
    ...overrides,
});

describe('tabOf', () => {
    it.each([
        ['paid', 'toShip'],
        ['shipped', 'shipped'],
        ['pending', 'other'],
        ['failed', 'other'],
        ['expired', 'other'],
        ['cancelled', 'other'],
    ])('puts %s in %s', (status, tab) => {
        expect(tabOf(status)).toBe(tab);
    });
});

describe('tabCounts and ordersOfTab', () => {
    const all = [order(), order({ id: 'o2', status: 'paid' }), order({ id: 'o3', status: 'shipped' }), order({ id: 'o4', status: 'expired' }), order({ id: 'o5', status: 'cancelled' })];

    it('counts each tab', () => {
        expect(tabCounts(all)).toEqual({ toShip: 2, shipped: 1, other: 2 });
    });

    it('counts zero for an empty list', () => {
        expect(tabCounts([])).toEqual({ toShip: 0, shipped: 0, other: 0 });
    });

    it('keeps the order of the list inside a tab', () => {
        expect(ordersOfTab(all, 'other').map((entry) => entry.id)).toEqual(['o4', 'o5']);
    });
});

describe('searchOrders', () => {
    const all = [order(), order({ id: 'o2', reference: 'ML-ZZ99-YY88', name: 'John Smith', email: 'js@shop.test' })];

    it('keeps everything for a blank query', () => {
        expect(searchOrders(all, '  ')).toEqual(all);
    });

    it.each([['ab12'], ['eloise'], ['ÉLOÏSE'], ['example.test'], ['ml-ab12 martin']])('finds the first order by %s', (query) => {
        expect(searchOrders(all, query).map((entry) => entry.id)).toEqual(['o1']);
    });

    it('finds by name and by email', () => {
        expect(searchOrders(all, 'smith').map((entry) => entry.id)).toEqual(['o2']);
        expect(searchOrders(all, 'js@shop').map((entry) => entry.id)).toEqual(['o2']);
    });

    it('finds nothing for an unknown word', () => {
        expect(searchOrders(all, 'zebra')).toEqual([]);
    });

    it('does not search the address', () => {
        expect(searchOrders(all, 'lilas')).toEqual([]);
    });
});

describe('actionsFor', () => {
    it('ships and cancels a paid order', () => {
        expect(actionsFor(order({ status: 'paid' }))).toEqual(['ship', 'cancel']);
    });

    it('only cancels a shipped order', () => {
        expect(actionsFor(order({ status: 'shipped' }))).toEqual(['cancel']);
    });

    it.each(['pending', 'failed', 'expired'])('checks the payment of a %s order that has a checkout', (status) => {
        expect(actionsFor(order({ status }))).toEqual(['checkPayment']);
    });

    it('does not check the payment without a checkout', () => {
        expect(actionsFor(order({ status: 'expired', hasCheckout: false }))).toEqual([]);
    });

    it('offers nothing on a cancelled order', () => {
        expect(actionsFor(order({ status: 'cancelled' }))).toEqual([]);
    });

    it('never checks the payment of a paid order', () => {
        expect(actionsFor(order({ status: 'paid' }))).not.toContain('checkPayment');
    });

    it('adds the deletion of a test order last', () => {
        expect(actionsFor(order({ status: 'paid', test: true }))).toEqual(['ship', 'cancel', 'delete']);
        expect(actionsFor(order({ status: 'cancelled', test: true }))).toEqual(['delete']);
    });
});

describe('statusTone', () => {
    it.each([
        ['paid', 'warning'],
        ['shipped', 'success'],
        ['pending', 'neutral'],
        ['expired', 'neutral'],
        ['failed', 'danger'],
        ['cancelled', 'danger'],
    ])('%s is %s', (status, tone) => {
        expect(statusTone(status)).toBe(tone);
    });
});

describe('countryName', () => {
    it('names a country in the given language', () => {
        expect(countryName('FR', 'en-GB')).toBe('France');
        expect(countryName('DE', 'fr-FR')).toBe('Allemagne');
    });

    it('falls back on the code', () => {
        expect(countryName('??', 'en-GB')).toBe('??');
    });
});

describe('addressLines', () => {
    it('writes the address as on a parcel', () => {
        expect(addressLines(order(), 'France')).toEqual(['Éloïse Martin', '12 rue des Lilas', '75011 Paris', 'France']);
    });

    it('adds the second line and the region when there are some', () => {
        expect(addressLines(order({ line2: 'Bât. B', region: 'Île-de-France' }), 'France')).toEqual(['Éloïse Martin', '12 rue des Lilas', 'Bât. B', '75011 Paris', 'Île-de-France', 'France']);
    });
});
