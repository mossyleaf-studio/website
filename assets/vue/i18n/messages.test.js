import { describe, expect, it } from 'vitest';

const files = import.meta.glob('./*/*.json', { eager: true, import: 'default' });

const flatten = (messages, prefix = '') =>
    Object.entries(messages).flatMap(([key, value]) => (typeof value === 'string' ? [[prefix + key, value]] : flatten(value, `${prefix}${key}.`)));

const placeholders = (messages) => Object.fromEntries(flatten(messages).map(([key, text]) => [key, [...new Set(text.match(/\{[^}]*\}/g))].sort()]));

const namespaces = [...new Set(Object.keys(files).map((path) => path.split('/')[2]))];

describe('admin translations', () => {
    it('finds the namespaces of both languages', () => {
        expect(namespaces.length).toBeGreaterThan(0);
        expect(Object.keys(files).sort()).toEqual(['en', 'fr'].flatMap((language) => namespaces.map((namespace) => `./${language}/${namespace}`)).sort());
    });

    it.each(namespaces)('%s has the same keys and placeholders in English and French', (namespace) => {
        expect(placeholders(files[`./fr/${namespace}`])).toEqual(placeholders(files[`./en/${namespace}`]));
    });
});
