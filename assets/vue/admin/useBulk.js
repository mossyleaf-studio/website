import { useI18n } from 'vue-i18n';
import { useToast } from '../composables/useToast.js';
import { bulkMessage, planBulk, runBulk } from './bulk.js';
import { bulkProgress } from './useSelection.js';

export function useBulk(selection) {
    const { t } = useI18n();
    const toast = useToast();

    const targets = (action) => planBulk(selection.items.value, action.skip).targets.length;

    async function run(action) {
        if (bulkProgress.value) return;
        const items = selection.items.value;
        bulkProgress.value = { done: 0, total: targets(action) };
        try {
            const outcome = await runBulk(items, action, (done, total) => { bulkProgress.value = { done, total }; });
            selection.ids.value = selection.ids.value.filter((id) => outcome.failed.includes(id));
            (outcome.failed.length ? toast.error : toast.success)(bulkMessage(t, action.done, outcome));
        } finally {
            bulkProgress.value = null;
        }
    }

    return { progress: bulkProgress, targets, run };
}
