import { describe, expect, it } from 'vitest';
import { headState, keepVisible, tick, tickAll } from './selection.js';

describe('tick', () => {
    it('adds an id once', () => {
        expect(tick(['a'], 'b', true)).toEqual(['a', 'b']);
        expect(tick(['a'], 'a', true)).toEqual(['a']);
    });

    it('removes an id', () => {
        expect(tick(['a', 'b'], 'a', false)).toEqual(['b']);
        expect(tick(['b'], 'a', false)).toEqual(['b']);
    });
});

describe('headState', () => {
    it('is unchecked when nothing visible is ticked', () => {
        expect(headState([], ['a', 'b'])).toBe(false);
        expect(headState(['z'], ['a', 'b'])).toBe(false);
        expect(headState([], [])).toBe(false);
    });

    it('is checked when every visible item is ticked', () => {
        expect(headState(['a', 'b', 'z'], ['a', 'b'])).toBe(true);
    });

    it('is mixed when only some are ticked', () => {
        expect(headState(['a'], ['a', 'b'])).toBe('indeterminate');
    });
});

describe('tickAll', () => {
    it('ticks what is visible and keeps the rest', () => {
        expect(tickAll(['z', 'a'], ['a', 'b'], true)).toEqual(['z', 'a', 'b']);
    });

    it('unticks only what is visible', () => {
        expect(tickAll(['z', 'a', 'b'], ['a', 'b'], false)).toEqual(['z']);
    });
});

describe('keepVisible', () => {
    it('drops what left the list and keeps the order of the selection', () => {
        expect(keepVisible(['c', 'a', 'gone'], ['a', 'b', 'c'])).toEqual(['c', 'a']);
    });
});
