export const CART_KEY = 'mossyleaf.cart';
export const MAX_QUANTITY = 10;
export const MAX_LINES = 30;

const MAX_SKU = 320;

export const emptyCart = () => ({ lines: [], country: null });

const bounded = (quantity, limit = MAX_QUANTITY) => Math.min(limit, MAX_QUANTITY, Math.max(1, Math.trunc(quantity) || 1));

const isLine = (line) => typeof line?.sku === 'string' && line.sku !== '' && line.sku.length <= MAX_SKU && Number.isFinite(line.quantity);

export function addLine(cart, { sku, quantity }, limit = MAX_QUANTITY) {
    if (limit < 1) return cart;
    if (cart.lines.some((line) => line.sku === sku)) {
        return { ...cart, lines: cart.lines.map((line) => (line.sku === sku ? { sku, quantity: Math.max(line.quantity, bounded(line.quantity + bounded(quantity), limit)) } : line)) };
    }
    if (cart.lines.length >= MAX_LINES) return cart;

    return { ...cart, lines: [...cart.lines, { sku, quantity: bounded(quantity, limit) }] };
}

export const setQuantity = (cart, sku, quantity) => ({ ...cart, lines: cart.lines.map((line) => (line.sku === sku ? { sku, quantity: bounded(quantity) } : line)) });

export const removeLine = (cart, sku) => ({ ...cart, lines: cart.lines.filter((line) => line.sku !== sku) });

export const setCountry = (cart, country) => ({ ...cart, country });

export const cartUnits = (cart) => cart.lines.reduce((units, line) => units + line.quantity, 0);

export function readCart(stored) {
    let value;
    try {
        value = JSON.parse(stored);
    } catch {
        return emptyCart();
    }
    if (!Array.isArray(value?.lines)) return emptyCart();

    const country = typeof value.country === 'string' && /^[A-Z]{2}$/.test(value.country) ? value.country : null;

    return value.lines.filter(isLine).reduce((cart, line) => addLine(cart, line), { lines: [], country });
}

export const settle = (cart, quote) => ({ ...cart, lines: quote.lines.map(({ sku, quantity }) => ({ sku, quantity })) });

export const pricedLines = (cart, quote) =>
    cart.lines.flatMap(({ sku, quantity }) => {
        const line = quote.lines.find((entry) => entry.sku === sku);
        return line ? [{ ...line, quantity, lineTotal: line.unitPrice * quantity }] : [];
    });

export function issueNames(issues, type) {
    const mine = issues.filter((issue) => issue.type === type);

    return { titles: [...new Set(mine.map((issue) => issue.title).filter(Boolean))], unnamed: mine.filter((issue) => !issue.title).length };
}
