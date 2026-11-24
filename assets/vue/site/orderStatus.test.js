import { describe, expect, it } from 'vitest';
import { POLL_WINDOW, keepAsking, statusView, trackingOf } from './orderStatus.js';

const order = (overrides = {}) => ({ reference: 'ML-ABCD-EFGH', status: 'pending', test: false, paymentUrl: '/_fake/sumup/fake-1', lines: [], goods: 3000, shipping: 450, total: 3450, placedAt: '2026-11-20T10:00:00+00:00', ...overrides });

describe('statusView', () => {
    it('shows a pending order as waiting for its payment, with the way back to the payment and a reload link', () => {
        expect(statusView(order())).toEqual({ state: 'pending', progress: true, payUrl: '/_fake/sumup/fake-1', backToCart: false, reload: true, settled: false });
    });

    it('drops the payment link once the checkout is no longer open', () => {
        expect(statusView(order({ paymentUrl: null })).payUrl).toBeNull();
    });

    it('says a pending order is still waiting once the page stopped asking', () => {
        expect(statusView(order(), { stalled: true })).toEqual({ state: 'stalled', progress: false, payUrl: '/_fake/sumup/fake-1', backToCart: false, reload: true, settled: false });
    });

    it('thanks the customer for a paid order and empties the cart', () => {
        expect(statusView(order({ status: 'paid', paymentUrl: null }))).toEqual({ state: 'paid', progress: false, payUrl: null, backToCart: false, reload: false, settled: true });
    });

    it('sends a failed or expired order back to the cart, which is kept', () => {
        for (const status of ['failed', 'expired']) {
            expect(statusView(order({ status, paymentUrl: null })), status).toEqual({ state: status, progress: false, payUrl: null, backToCart: true, reload: false, settled: false });
        }
    });

    it('never offers the payment outside a pending order', () => {
        for (const status of ['paid', 'failed', 'expired', 'shipped', 'cancelled']) {
            expect(statusView(order({ status })).payUrl, status).toBeNull();
        }
    });

    it('states a shipped or cancelled order plainly', () => {
        expect(statusView(order({ status: 'shipped', paymentUrl: null }))).toEqual({ state: 'shipped', progress: false, payUrl: null, backToCart: false, reload: false, settled: true });
        expect(statusView(order({ status: 'cancelled', paymentUrl: null }))).toEqual({ state: 'cancelled', progress: false, payUrl: null, backToCart: false, reload: false, settled: false });
    });

    it('ignores the stalled flag once the order has settled', () => {
        expect(statusView(order({ status: 'paid' }), { stalled: true }).state).toBe('paid');
    });
});

describe('keepAsking', () => {
    const start = 1_000_000;

    it('asks again while the order is pending, for ten minutes at most', () => {
        expect(keepAsking('pending', start, start)).toBe(true);
        expect(keepAsking('pending', start, start + POLL_WINDOW - 1)).toBe(true);
        expect(keepAsking('pending', start, start + POLL_WINDOW)).toBe(false);
    });

    it('stops on any other state', () => {
        for (const status of ['paid', 'failed', 'expired', 'shipped', 'cancelled']) {
            expect(keepAsking(status, start, start), status).toBe(false);
        }
    });
});

describe('trackingOf', () => {
    it('gives the tracking number of a shipped order that has one', () => {
        expect(trackingOf(order({ status: 'shipped', trackingNumber: 'LA123456789FR' }))).toBe('LA123456789FR');
    });

    it('gives nothing for a shipped order without one', () => {
        expect(trackingOf(order({ status: 'shipped', trackingNumber: null }))).toBeNull();
        expect(trackingOf(order({ status: 'shipped' }))).toBeNull();
    });

    it('never gives one in another state', () => {
        for (const status of ['pending', 'paid', 'failed', 'expired', 'cancelled']) {
            expect(trackingOf(order({ status, trackingNumber: 'LA123456789FR' })), status).toBeNull();
        }
    });
});
