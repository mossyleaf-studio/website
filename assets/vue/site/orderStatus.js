export const POLL_INTERVAL = 4000;
export const POLL_WINDOW = 10 * 60 * 1000;

export const keepAsking = (status, startedAt, now) => status === 'pending' && now - startedAt < POLL_WINDOW;

export function statusView(order, { stalled = false } = {}) {
    const pending = order.status === 'pending';
    const state = pending && stalled ? 'stalled' : order.status;

    return {
        state,
        progress: state === 'pending',
        payUrl: pending ? order.paymentUrl : null,
        backToCart: ['failed', 'expired'].includes(order.status),
        reload: pending,
        settled: ['paid', 'shipped'].includes(order.status),
    };
}
