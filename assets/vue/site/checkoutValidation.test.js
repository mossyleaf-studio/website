import { describe, expect, it } from 'vitest';
import { CHECKOUT_FIELDS, CHECKOUT_LIMITS, checkoutErrors, emptyDelivery, firstInvalid, needsRegion, orderPayload, serverErrors } from './checkoutValidation.js';

const filled = (overrides = {}) => ({
    ...emptyDelivery(),
    email: 'lou@example.com',
    name: 'Lou Moss',
    line1: '12 rue des Fougères',
    postalCode: '69001',
    city: 'Lyon',
    terms: true,
    ...overrides,
});
const france = { country: 'FR', zone: 'france' };
const world = { country: 'CA', zone: 'world' };

describe('checkoutErrors', () => {
    it('accepts a complete delivery form', () => {
        expect(checkoutErrors(filled(), france)).toEqual({});
    });

    it('asks for every required field and the terms', () => {
        expect(checkoutErrors(emptyDelivery(), france)).toEqual({
            email: { key: 'form.required' },
            name: { key: 'form.required' },
            line1: { key: 'form.required' },
            postalCode: { key: 'form.required' },
            city: { key: 'form.required' },
            terms: { key: 'checkout.errors.terms' },
        });
    });

    it('treats blank text as empty', () => {
        expect(checkoutErrors(filled({ name: '   ', city: '\t' }), france)).toEqual({ name: { key: 'form.required' }, city: { key: 'form.required' } });
    });

    it('leaves the phone, the second line and the region optional in Europe', () => {
        expect(checkoutErrors(filled({ phone: '', line2: '', region: '' }), { country: 'DE', zone: 'eu' })).toEqual({});
    });

    it('checks the shape of the email', () => {
        for (const email of ['lou', 'lou@', '@example.com', 'lou@example', 'lou@@example.com', 'lou @example.com']) {
            expect(checkoutErrors(filled({ email }), france), email).toEqual({ email: { key: 'form.email' } });
        }
        expect(checkoutErrors(filled({ email: '  lou.moss+shop@example.co.uk ' }), france)).toEqual({});
    });

    it('keeps every field within the limits of the order', () => {
        const long = Object.fromEntries(Object.entries(CHECKOUT_LIMITS).map(([field, max]) => [field, 'a'.repeat(max + 1)]));
        const errors = checkoutErrors(filled({ ...long, email: `${'a'.repeat(250)}@example.com` }), world);

        for (const [field, max] of Object.entries(CHECKOUT_LIMITS)) {
            expect(errors[field], field).toEqual({ key: 'form.tooLong', max });
        }
    });

    it('counts characters, not code units, and accepts the limit itself', () => {
        expect(checkoutErrors(filled({ city: '🍄'.repeat(CHECKOUT_LIMITS.city) }), france)).toEqual({});
    });

    it('refuses line breaks', () => {
        expect(checkoutErrors(filled({ line1: '12 rue\ndes Fougères' }), france)).toEqual({ line1: { key: 'form.lineBreak' } });
    });

    it('refuses control characters and line separators, as the server does', () => {
        expect(checkoutErrors(filled({ name: 'Ana\u0000Lou', city: 'Lyon\u001b', line2: 'Bât\u2028B', email: 'lou\u007f@example.test' }), france)).toEqual({
            name: { key: 'form.lineBreak' },
            city: { key: 'form.lineBreak' },
            line2: { key: 'form.lineBreak' },
            email: { key: 'form.lineBreak' },
        });
    });

    it('asks for the region when the country is outside Europe', () => {
        expect(checkoutErrors(filled(), world)).toEqual({ region: { key: 'form.required' } });
        expect(checkoutErrors(filled({ region: 'Québec' }), world)).toEqual({});
    });

    it('asks for a country when none is chosen', () => {
        expect(checkoutErrors(filled(), { country: null, zone: null })).toEqual({ country: { key: 'checkout.errors.country' } });
    });

    it('mirrors the limits of the delivery address', () => {
        expect(CHECKOUT_LIMITS).toEqual({ email: 254, name: 120, phone: 32, line1: 120, line2: 120, postalCode: 16, city: 80, region: 80 });
    });
});

describe('needsRegion', () => {
    it('is only true for the world zone', () => {
        expect(['france', 'eu', 'world', null].map(needsRegion)).toEqual([false, false, true, false]);
    });
});

describe('orderPayload', () => {
    const cart = { lines: [{ sku: 'PRT-001-A4', quantity: 2 }], country: 'FR', expectedTotal: 3450, zone: 'france' };

    it('sends the lines, the country, the total shown and the trimmed form', () => {
        expect(orderPayload(filled({ email: ' lou@example.com ', name: ' Lou Moss ', phone: ' 06 12 34 56 78 ', line2: '  ', region: 'ignored' }), cart)).toEqual({
            lines: [{ sku: 'PRT-001-A4', quantity: 2 }],
            country: 'FR',
            expectedTotal: 3450,
            email: 'lou@example.com',
            name: 'Lou Moss',
            phone: '06 12 34 56 78',
            line1: '12 rue des Fougères',
            line2: null,
            postalCode: '69001',
            city: 'Lyon',
            region: null,
            terms: true,
        });
    });

    it('sends the region only outside Europe', () => {
        expect(orderPayload(filled({ region: ' Québec ' }), { ...cart, country: 'CA', zone: 'world' }).region).toBe('Québec');
    });

    it('sends only the sku and the quantity of each line', () => {
        expect(orderPayload(filled(), { ...cart, lines: [{ sku: 'A', quantity: 1, title: 'Moss' }] }).lines).toEqual([{ sku: 'A', quantity: 1 }]);
    });
});

describe('serverErrors', () => {
    it('keeps the first violation of each field the form shows', () => {
        expect(
            serverErrors([
                { propertyPath: 'email', title: 'Enter a valid email address.' },
                { propertyPath: 'email', title: 'Too long.' },
                { propertyPath: 'postalCode', title: 'Fill in this field.' },
                { propertyPath: 'terms', title: 'Accept the terms.' },
                { propertyPath: 'lines[0].sku', title: 'Unknown.' },
                { propertyPath: 'expectedTotal', title: 'Wrong.' },
            ]),
        ).toEqual({ email: { text: 'Enter a valid email address.' }, postalCode: { text: 'Fill in this field.' }, terms: { text: 'Accept the terms.' } });
    });

    it('answers nothing without violations', () => {
        expect(serverErrors([])).toEqual({});
    });
});

describe('firstInvalid', () => {
    it('follows the order of the form', () => {
        expect(firstInvalid({ terms: {}, city: {}, region: {} })).toBe('city');
        expect(firstInvalid({})).toBeNull();
        expect(CHECKOUT_FIELDS).toEqual(['email', 'name', 'phone', 'line1', 'line2', 'postalCode', 'city', 'country', 'region', 'terms']);
    });
});
