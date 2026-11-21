import { computed, onScopeDispose, ref, toValue, watch } from 'vue';
import { headState, keepVisible, tick, tickAll } from './selection.js';

export const bulkProgress = ref(null);

const dialogOpen = () => document.querySelector('[role="dialog"], [role="alertdialog"]') !== null;
const KEEPS_ESCAPE = 'input, textarea, select, [role="listbox"], [role="menu"], [data-reka-popper-content-wrapper], .toast-host';

export function useSelection(visible) {
    const ids = ref([]);
    const locked = computed(() => bulkProgress.value !== null);
    const visibleIds = computed(() => toValue(visible).map((item) => item.id));
    const idsOf = (subset) => (subset ? toValue(subset).map((item) => item.id) : visibleIds.value);

    watch(visibleIds, (now) => { ids.value = keepVisible(ids.value, now); });

    const change = (next) => { if (!locked.value) ids.value = next; };
    const clear = () => change([]);

    function onKey(event) {
        if (event.key !== 'Escape' || event.defaultPrevented || ids.value.length === 0 || dialogOpen()) return;
        if (event.target?.closest?.(KEEPS_ESCAPE)) return;
        clear();
    }

    window.addEventListener('keydown', onKey);
    onScopeDispose(() => window.removeEventListener('keydown', onKey));

    return {
        ids,
        locked,
        items: computed(() => toValue(visible).filter((item) => ids.value.includes(item.id))),
        count: computed(() => ids.value.length),
        has: (id) => ids.value.includes(id),
        set: (id, checked) => change(tick(ids.value, id, checked)),
        head: (subset) => headState(ids.value, idsOf(subset)),
        setAll: (checked, subset) => change(tickAll(ids.value, idsOf(subset), checked)),
        clear,
    };
}
