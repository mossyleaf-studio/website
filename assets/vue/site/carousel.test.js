import { describe, expect, it } from 'vitest';
import { carouselState } from './carousel.js';

describe('carouselState', () => {
    it('needs no button when every card fits', () => {
        expect(carouselState({ scrollLeft: 0, clientWidth: 800, scrollWidth: 800 })).toEqual({ needed: false, previous: false, next: false });
    });

    it('ignores a rounding pixel', () => {
        expect(carouselState({ scrollLeft: 0, clientWidth: 800, scrollWidth: 800.6 })).toEqual({ needed: false, previous: false, next: false });
    });

    it('only goes forward at the start', () => {
        expect(carouselState({ scrollLeft: 0, clientWidth: 800, scrollWidth: 2000 })).toEqual({ needed: true, previous: false, next: true });
    });

    it('goes both ways in the middle', () => {
        expect(carouselState({ scrollLeft: 600, clientWidth: 800, scrollWidth: 2000 })).toEqual({ needed: true, previous: true, next: true });
    });

    it('only goes back at the end, even a fraction of a pixel short of it', () => {
        expect(carouselState({ scrollLeft: 1199.5, clientWidth: 800, scrollWidth: 2000 })).toEqual({ needed: true, previous: true, next: false });
    });

    it('treats a fraction of a pixel from the start as the start', () => {
        expect(carouselState({ scrollLeft: 0.4, clientWidth: 800, scrollWidth: 2000 })).toEqual({ needed: true, previous: false, next: true });
    });
});
