<script setup>
import { computed, nextTick, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import ConfirmButton from '../../components/ui/ConfirmButton.vue';
import SelectionBar from '../../components/ui/SelectionBar.vue';
import { useBulk } from '../useBulk.js';

const props = defineProps({
    selection: { type: Object, required: true },
    actions: { type: Array, required: true },
    noun: { type: String, required: true },
});

const { t } = useI18n();
const bulk = useBulk(props.selection);
const bar = useTemplateRef('bar');

async function settleFocus(page) {
    await nextTick();
    const target = props.selection.count.value > 0
        ? bar.value?.root?.querySelector('button:not(:disabled)')
        : page?.querySelector('[role="checkbox"]:not(:disabled)');
    target?.focus();
}

function clear() {
    const page = bar.value?.root?.parentElement;
    props.selection.clear();
    settleFocus(page);
}

async function run(action) {
    const page = bar.value?.root?.parentElement;
    await bulk.run(action);
    settleFocus(page);
}

const count = computed(() => props.selection.count.value);
const summary = computed(() => (bulk.progress.value
    ? t('admin.selection.running', bulk.progress.value)
    : t(props.noun, { count: count.value }, count.value)));

const buttons = computed(() => props.actions.map((action) => {
    const targets = bulk.targets(action);
    return { action, targets, label: action.label(targets), disabled: targets === 0 || bulk.progress.value !== null };
}));
</script>

<template>
    <SelectionBar ref="bar" :count="count" :summary="summary" :busy="bulk.progress.value !== null" @clear="clear">
        <span v-for="{ action, targets, label, disabled } in buttons" :key="action.key" class="bulk-bar__action" :data-test="`bulk-${action.key}`">
            <ConfirmButton
                v-if="action.confirm"
                :variant="action.tone ?? 'primary'"
                :confirm-variant="action.tone ?? 'primary'"
                :label="label"
                :confirm-label="label"
                :message="action.confirm(targets)"
                :disabled="disabled"
                @confirm="run(action)"
            />
            <BaseButton v-else :variant="action.tone ?? 'primary'" :disabled="disabled" @click="run(action)">{{ label }}</BaseButton>
        </span>
    </SelectionBar>
</template>

<style scoped>
.bulk-bar__action { display: contents; }
</style>
