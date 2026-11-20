import { describe, expect, it } from 'vitest';
import ranges from '../../../tests/Fixtures/date-ranges.json';
import { formatRange, stubOf } from './dates.js';

describe('formatRange', () => {
    it.each(ranges.flatMap((range) => ['en', 'fr'].map((locale) => [locale, range.startsOn, range.endsOn, range[locale]])))('%s %s to %s reads %s', (locale, startsOn, endsOn, expected) => {
        expect(formatRange(startsOn, endsOn, locale)).toBe(expected);
    });

    it('formats a single day the same way whatever the time zone', () => {
        expect(formatRange('2027-01-01', '2027-01-01', 'fr')).toBe('1 janvier 2027');
    });
});

describe('stubOf', () => {
    it('gives the day range and the month, without the current year', () => {
        expect(stubOf('2027-06-12', '2027-06-14', 'fr', 2027)).toEqual({ days: '12–14', month: 'juin', year: null });
    });

    it('gives a single day', () => {
        expect(stubOf('2027-06-12', '2027-06-12', 'en', 2027)).toEqual({ days: '12', month: 'June', year: null });
    });

    it('shows the year when it is not the current one', () => {
        expect(stubOf('2027-08-01', '2027-08-03', 'fr', 2026)).toEqual({ days: '1–3', month: 'août', year: '2027' });
    });

    it('shortens the months of a range across months', () => {
        expect(stubOf('2027-06-30', '2027-07-02', 'en', 2027)).toEqual({ days: '30–2', month: 'Jun–Jul', year: null });
    });

    it('gives both years of a range across years', () => {
        expect(stubOf('2027-12-30', '2028-01-02', 'fr', 2027)).toEqual({ days: '30–2', month: 'déc.–janv.', year: '2027–2028' });
    });
});
