import { describe, expect, it } from 'vitest';
import { parseCents, printCents } from './amount.js';

describe('parseCents', () => {
    it('reads a comma or a dot as integer cents', () => {
        expect(parseCents('12,5')).toBe(1250);
        expect(parseCents('12.50')).toBe(1250);
        expect(parseCents('12')).toBe(1200);
        expect(parseCents(' 0,05 ')).toBe(5);
        expect(parseCents('19.99')).toBe(1999);
        expect(parseCents('1.1')).toBe(110);
    });

    it('reads an empty field as null', () => {
        expect(parseCents('')).toBeNull();
        expect(parseCents('   ')).toBeNull();
    });

    it('refuses negatives, words and more than two decimals', () => {
        expect(parseCents('-3')).toBe(false);
        expect(parseCents('abc')).toBe(false);
        expect(parseCents('1,234')).toBe(false);
        expect(parseCents('1,2,3')).toBe(false);
        expect(parseCents('NaN')).toBe(false);
        expect(parseCents('1e3')).toBe(false);
    });
});

describe('parseCents with a maximum', () => {
    it('refuses an amount above it', () => {
        expect(parseCents('10000', 1000000)).toBe(1000000);
        expect(parseCents('10000,01', 1000000)).toBe(false);
        expect(parseCents('99999999999999999')).toBe(false);
    });
});

describe('printCents', () => {
    it('prints cents as a plain amount with a comma or a dot', () => {
        expect(printCents(1250, ',')).toBe('12,50');
        expect(printCents(1200, '.')).toBe('12.00');
        expect(printCents(5, '.')).toBe('0.05');
        expect(printCents(0, ',')).toBe('0,00');
    });

    it('prints null as empty', () => {
        expect(printCents(null, ',')).toBe('');
    });
});
