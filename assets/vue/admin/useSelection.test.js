import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { effectScope, nextTick, ref } from 'vue';
import { useSelection } from './useSelection.js';

let handlers;
let dialog;
let scope;

beforeEach(() => {
    handlers = {};
    dialog = false;
    globalThis.window = {
        addEventListener: (type, handler) => { handlers[type] = handler; },
        removeEventListener: (type) => { delete handlers[type]; },
    };
    globalThis.document = { querySelector: () => (dialog ? {} : null) };
    scope = effectScope();
});

afterEach(() => {
    scope.stop();
});

const escape = (target = { closest: () => null }) => handlers.keydown({ key: 'Escape', defaultPrevented: false, target });

function selectionOf(rows) {
    return scope.run(() => useSelection(rows));
}

describe('useSelection', () => {
    it('drops the ticked items that leave the visible list', async () => {
        const rows = ref([{ id: 'a' }, { id: 'b' }]);
        const selection = selectionOf(rows);
        selection.setAll(true);
        rows.value = [{ id: 'b' }];
        await nextTick();
        expect(selection.ids.value).toEqual(['b']);
        expect(selection.head()).toBe(true);
    });

    it('clears on Escape, but not behind an open dialog', () => {
        const selection = selectionOf(ref([{ id: 'a' }]));
        selection.set('a', true);
        dialog = true;
        escape();
        expect(selection.count.value).toBe(1);
        dialog = false;
        escape();
        expect(selection.count.value).toBe(0);
    });

    it('keeps the selection when Escape closes a list box or leaves a field', () => {
        const selection = selectionOf(ref([{ id: 'a' }]));
        selection.set('a', true);
        escape({ closest: (selector) => (selector.includes('[role="listbox"]') ? {} : null) });
        expect(selection.count.value).toBe(1);
    });

    it('stops listening once its page is gone', () => {
        selectionOf(ref([]));
        scope.stop();
        expect(handlers.keydown).toBeUndefined();
    });
});
