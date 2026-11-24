import { nextTick, ref } from 'vue';
import { describe, expect, it } from 'vitest';
import { usePagination } from './usePagination.js';

const numbers = (count) => ref(Array.from({ length: count }, (_, index) => index + 1));

describe('usePagination', () => {
    it('offers 20, 50 and 100 and starts at 20', () => {
        expect(usePagination(numbers(5)).pageSize.value).toBe(20);
    });

    it('slices the page and words the range', () => {
        const pager = usePagination(numbers(45));
        pager.goTo(2);
        expect(pager.pageItems.value).toHaveLength(20);
        expect(pager.pageItems.value[0]).toBe(21);
        expect([pager.from.value, pager.to.value, pager.total.value, pager.pageCount.value]).toEqual([21, 40, 45, 3]);
        pager.goTo(3);
        expect([pager.from.value, pager.to.value, pager.pageItems.value.length]).toEqual([41, 45, 5]);
    });

    it('clamps goTo and a page left past the end by a shorter list', async () => {
        const items = numbers(45);
        const pager = usePagination(items);
        pager.goTo(99);
        expect(pager.page.value).toBe(3);
        items.value = items.value.slice(0, 25);
        await nextTick();
        expect(pager.page.value).toBe(2);
    });

    it('goes back to page 1 when the page size changes', async () => {
        const pager = usePagination(numbers(120));
        pager.goTo(3);
        pager.pageSize.value = 50;
        await nextTick();
        expect(pager.page.value).toBe(1);
    });

    it('keeps the page asked for while the list is still empty', () => {
        const pager = usePagination(ref([]), 20, { page: ref(3), pageSize: ref(20) });
        expect(pager.page.value).toBe(3);
        expect([pager.from.value, pager.to.value]).toEqual([0, 0]);
    });
});
