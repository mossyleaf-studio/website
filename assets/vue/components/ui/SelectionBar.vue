<script setup>
import { useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from './BaseButton.vue';

defineProps({
    count: { type: Number, required: true },
    summary: { type: String, required: true },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['clear']);
const { t } = useI18n();
const root = useTemplateRef('root');
defineExpose({ root });
</script>

<template>
    <Transition name="selection-bar">
        <div v-if="count > 0 || busy" ref="root" class="selection-bar" role="region" :aria-label="t('admin.selection.label')" :aria-busy="busy || undefined" data-test="selection-bar">
            <span class="selection-bar__count" aria-hidden="true" data-test="selection-count">{{ summary }}</span>
            <div class="selection-bar__actions">
                <slot />
            </div>
            <BaseButton class="selection-bar__clear" variant="inverse" :disabled="busy" data-test="selection-clear" @click="emit('clear')">{{ t('admin.selection.clear') }}</BaseButton>
        </div>
    </Transition>
    <span class="visually-hidden" role="status">{{ count > 0 || busy ? summary : '' }}</span>
</template>

<style scoped>
.selection-bar {
    position: sticky;
    bottom: calc(var(--space-4) + env(safe-area-inset-bottom));
    z-index: 5;
    display: grid;
    grid-template-columns: max-content minmax(0, 1fr) max-content;
    grid-template-areas: "count actions clear";
    align-items: center;
    gap: var(--space-3);
    width: fit-content;
    max-width: 100%;
    margin: var(--space-4) auto 0;
    padding: var(--space-2) var(--space-2) var(--space-2) var(--space-4);
    background: var(--color-ink);
    color: var(--color-surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.selection-bar__count { grid-area: count; font-weight: 600; }

.selection-bar__actions {
    grid-area: actions;
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2) var(--space-3);
}

.selection-bar__clear { grid-area: clear; }

@media (max-width: 40rem) {
    .selection-bar {
        grid-template-columns: minmax(0, 1fr) max-content;
        grid-template-areas:
            "count clear"
            "actions actions";
    }
}

.selection-bar-enter-active,
.selection-bar-leave-active { transition: opacity var(--transition), transform var(--transition); }
.selection-bar-enter-from,
.selection-bar-leave-to { opacity: 0; transform: translateY(0.5rem); }

@media (prefers-reduced-motion: reduce) {
    .selection-bar-enter-active,
    .selection-bar-leave-active { transition: none; }
}
</style>
