import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, ref } from 'vue';
import { useToast } from '../composables/useToast.js';
import { useBulk } from './useBulk.js';
import { useSelection } from './useSelection.js';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key, values) => `${key}:${values.count}` }) }));

let scope;

beforeEach(() => {
    globalThis.window = { addEventListener: () => {}, removeEventListener: () => {} };
    globalThis.document = { querySelector: () => null };
    scope = effectScope();
});

afterEach(() => {
    scope.stop();
});

const mount = (rows) => scope.run(() => {
    const selection = useSelection(rows);
    return { selection, bulk: useBulk(selection) };
});

function deferred() {
    let resolve;
    const promise = new Promise((done) => { resolve = done; });
    return { promise, resolve };
}

describe('useBulk', () => {
    it('keeps ticked only what was refused, and one message says it', async () => {
        const { selection, bulk } = mount(ref([{ id: 'a' }, { id: 'b' }, { id: 'c' }]));
        selection.setAll(true);
        await bulk.run({ done: 'done', perform: async (item) => { if (item.id === 'b') throw new Error('No'); } });
        expect(selection.ids.value).toEqual(['b']);
        expect(useToast().toasts.at(-1)).toMatchObject({ type: 'error', message: 'done:2 admin.bulk.failed:1' });
    });

    it('does not tick again a refused item that left the list during the run', async () => {
        const rows = ref([{ id: 'a' }, { id: 'b' }, { id: 'c' }]);
        const { selection, bulk } = mount(rows);
        selection.setAll(true);
        await bulk.run({
            done: 'done',
            perform: async (item) => {
                if (item.id === 'a') {
                    rows.value = [{ id: 'b' }];
                    await nextTick();
                }
                throw new Error('No');
            },
        });
        expect(selection.ids.value).toEqual(['b']);
        expect(selection.count.value).toBe(selection.items.value.length);
    });

    it('locks every list while a run goes on, even one opened after it started', async () => {
        const first = mount(ref([{ id: 'a' }]));
        first.selection.set('a', true);
        const gate = deferred();
        const running = first.bulk.run({ done: 'done', perform: () => gate.promise });

        const again = mount(ref([{ id: 'a' }]));
        expect(again.selection.locked.value).toBe(true);
        expect(again.bulk.progress.value).toEqual({ done: 0, total: 1 });
        again.selection.set('a', true);
        expect(again.selection.count.value).toBe(0);
        const perform = vi.fn();
        await again.bulk.run({ done: 'done', perform });
        expect(perform).not.toHaveBeenCalled();

        gate.resolve();
        await running;
        expect(again.selection.locked.value).toBe(false);
        expect(again.bulk.progress.value).toBeNull();
    });
});
