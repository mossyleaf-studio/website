import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

function fakeBar() {
    const classes = new Set();
    return {
        style: {},
        setAttribute: () => {},
        getBoundingClientRect: () => ({}),
        classList: { add: (name) => classes.add(name), remove: (name) => classes.delete(name) },
        get visible() {
            return classes.has('progress-bar--visible');
        },
    };
}

describe('the loading bar of a page change', () => {
    let bar;
    let page;
    let browser;

    const visit = () => page.dispatchEvent(new Event('turbo:visit'));
    const load = () => page.dispatchEvent(new Event('turbo:load'));

    beforeEach(async () => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'performance'] });
        vi.resetModules();
        bar = fakeBar();
        page = Object.assign(new EventTarget(), { createElement: () => bar, documentElement: { appendChild: () => {} } });
        browser = new EventTarget();
        vi.stubGlobal('document', page);
        vi.stubGlobal('window', browser);
        vi.stubGlobal('getComputedStyle', () => ({ transform: 'none' }));
        await import('./progressBar.js');
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('never shows for a page that comes within 150 ms', () => {
        visit();
        vi.advanceTimersByTime(140);
        load();
        vi.advanceTimersByTime(2000);

        expect(bar.visible).toBe(false);
        expect(bar.style.transform).toBeUndefined();
    });

    it('shows past 150 ms, crawls forward, then stays at least 300 ms', () => {
        visit();
        vi.advanceTimersByTime(160);
        expect(bar.visible).toBe(true);
        expect(bar.style.transform).toBe('scaleX(0.9)');

        load();
        vi.advanceTimersByTime(280);
        expect(bar.visible).toBe(true);

        vi.advanceTimersByTime(40);
        expect(bar.visible).toBe(false);
        expect(bar.style.transform).toBe('scaleX(1)');

        vi.advanceTimersByTime(500);
        expect(bar.style.transform).toBe('scaleX(0)');
    });

    it('ignores the load of the first page and ends each visit once', () => {
        load();
        visit();
        visit();
        vi.advanceTimersByTime(200);
        load();
        load();
        vi.advanceTimersByTime(1000);
        expect(bar.visible).toBe(false);

        visit();
        vi.advanceTimersByTime(200);
        expect(bar.visible).toBe(true);
    });

    it('starts a visit during the minimum wait without hiding the bar under it', () => {
        visit();
        vi.advanceTimersByTime(160);
        load();
        vi.advanceTimersByTime(100);
        visit();
        vi.advanceTimersByTime(400);
        expect(bar.visible).toBe(true);

        load();
        vi.advanceTimersByTime(400);
        expect(bar.visible).toBe(false);
    });

    it('ends a visit cut short by a full reload when the browser brings the page back', () => {
        visit();
        vi.advanceTimersByTime(160);
        expect(bar.visible).toBe(true);

        browser.dispatchEvent(Object.assign(new Event('pageshow'), { persisted: true }));
        vi.advanceTimersByTime(400);
        expect(bar.visible).toBe(false);

        visit();
        vi.advanceTimersByTime(200);
        expect(bar.visible).toBe(true);
    });
});
