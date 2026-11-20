import { describe, expect, it } from 'vitest';
import { relOf } from './links.js';

describe('relOf', () => {
    it.each(['https://www.instagram.com/mossyleaf.studio/', 'https://instagram.com/mossyleaf.studio'])('marks %s as the studio itself', (url) => {
        expect(relOf(url)).toBe('me');
    });

    it.each(['https://www.etsy.com/shop/mossyleafstudio', 'https://notinstagram.com/x', 'not a url'])('leaves %s alone', (url) => {
        expect(relOf(url)).toBeUndefined();
    });
});
