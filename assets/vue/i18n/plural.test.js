import { describe, expect, it } from 'vitest';
import { countingZeroAsOne } from './plural.js';

describe('countingZeroAsOne', () => {
    it.each([
        [0, 0],
        [1, 0],
        [2, 1],
        [12, 1],
    ])('picks form %i of two for %i', (count, form) => {
        expect(countingZeroAsOne(count, 2)).toBe(form);
    });

    it.each([
        [0, 0],
        [1, 1],
        [2, 2],
        [7, 2],
    ])('picks form %i of three (none, one, many) for %i', (count, form) => {
        expect(countingZeroAsOne(count, 3)).toBe(form);
    });
});
