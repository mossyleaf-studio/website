import { describe, expect, it } from 'vitest';
import { conventionForm, conventionPayload, splitConventions } from './conventions.js';

describe('splitConventions', () => {
    it('separates upcoming and past ones keeping the order', () => {
        const list = [{ id: 'a', upcoming: true }, { id: 'b', upcoming: false }, { id: 'c', upcoming: true }];
        expect(splitConventions(list)).toEqual({ upcoming: [list[0], list[2]], past: [list[1]] });
    });
});

describe('conventionForm and conventionPayload', () => {
    it('starts blank', () => {
        expect(conventionForm()).toEqual({ name: '', place: '', startsOn: '', endsOn: '', url: '', note: { en: '', fr: '' } });
    });

    it('round-trips a convention', () => {
        const convention = { id: 'x', name: 'N', place: 'P', startsOn: '2027-06-12', endsOn: '2027-06-14', url: null, note: 'en', noteFr: 'fr', upcoming: true };
        expect(conventionPayload(conventionForm(convention))).toEqual({ name: 'N', place: 'P', startsOn: '2027-06-12', endsOn: '2027-06-14', url: '', note: 'en', noteFr: 'fr' });
    });
});
