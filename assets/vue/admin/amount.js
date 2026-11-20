const AMOUNT = /^(\d+)(?:[.,](\d{1,2}))?$/;

export function parseCents(text, max = Number.MAX_SAFE_INTEGER) {
    const trimmed = text.trim();
    if (trimmed === '') {
        return null;
    }
    const match = AMOUNT.exec(trimmed);
    if (!match) {
        return false;
    }
    const cents = Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'));
    return cents <= max ? cents : false;
}

export function printCents(cents, separator) {
    if (cents === null) {
        return '';
    }
    return `${Math.floor(cents / 100)}${separator}${String(cents % 100).padStart(2, '0')}`;
}
