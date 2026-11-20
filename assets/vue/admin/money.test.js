import { describe, expect, it } from 'vitest';
import { formatCents } from './money.js';

const plain = (text) => text.replace(/\s/g, ' ');

describe('formatCents', () => {
    it('formats integer cents as euros in English', () => {
        expect(plain(formatCents(1500, 'en-GB'))).toBe('€15.00');
        expect(plain(formatCents(3450, 'en-GB'))).toBe('€34.50');
    });

    it('formats in French', () => {
        expect(plain(formatCents(3450, 'fr-FR'))).toBe('34,50 €');
    });

    it('keeps cents exact', () => {
        expect(plain(formatCents(5, 'en-GB'))).toBe('€0.05');
        expect(plain(formatCents(0, 'en-GB'))).toBe('€0.00');
    });
});
