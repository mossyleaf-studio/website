import { describe, expect, it } from 'vitest';
import { parseRichText } from './richText.js';

describe('parseRichText', () => {
    it('keeps plain text as one segment', () => {
        expect(parseRichText('Moss takes its time.')).toEqual([{ text: 'Moss takes its time.' }]);
    });

    it('turns [label](https://…) into links between text segments', () => {
        expect(parseRichText('Shop on [Etsy](https://www.etsy.com/shop/mossyleafstudio) and [Instagram](https://www.instagram.com/mossyleaf.studio/).')).toEqual([
            { text: 'Shop on ' },
            { text: 'Etsy', href: 'https://www.etsy.com/shop/mossyleafstudio' },
            { text: ' and ' },
            { text: 'Instagram', href: 'https://www.instagram.com/mossyleaf.studio/' },
            { text: '.' },
        ]);
    });

    it('leaves non-https links as plain text', () => {
        expect(parseRichText('[click](javascript:alert(1)) or [old](http://example.com)')).toEqual([
            { text: '[click](javascript:alert(1)) or [old](http://example.com)' },
        ]);
    });

    it('returns no segment for an empty text', () => {
        expect(parseRichText('')).toEqual([]);
    });
});
