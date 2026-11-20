const plain = (text) => text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

const TAB_OF = { paid: 'toShip', shipped: 'shipped' };
const TONE_OF = { paid: 'warning', shipped: 'success', failed: 'danger', cancelled: 'danger' };
const UNPAID = ['pending', 'failed', 'expired'];

export const TABS = ['toShip', 'shipped', 'other'];

export const tabOf = (status) => TAB_OF[status] ?? 'other';

export const statusTone = (status) => TONE_OF[status] ?? 'neutral';

export function tabCounts(orders) {
    const counts = { toShip: 0, shipped: 0, other: 0 };
    orders.forEach((order) => { counts[tabOf(order.status)] += 1; });
    return counts;
}

export const ordersOfTab = (orders, tab) => orders.filter((order) => tabOf(order.status) === tab);

export function searchOrders(orders, query) {
    const words = plain(query).split(/\s+/).filter(Boolean);
    return orders.filter((order) => {
        const haystack = plain(`${order.reference} ${order.name} ${order.email}`);
        return words.every((word) => haystack.includes(word));
    });
}

export function actionsFor(order) {
    return [
        order.status === 'paid' && 'ship',
        (order.status === 'paid' || order.status === 'shipped') && 'cancel',
        UNPAID.includes(order.status) && order.hasCheckout && 'checkPayment',
        order.test && 'delete',
    ].filter(Boolean);
}

export function countryName(code, locale) {
    try {
        return new Intl.DisplayNames(locale, { type: 'region' }).of(code) ?? code;
    } catch {
        return code;
    }
}

export function addressLines(order, country) {
    return [order.name, order.line1, order.line2, `${order.postalCode} ${order.city}`, order.region, country].filter(Boolean);
}
