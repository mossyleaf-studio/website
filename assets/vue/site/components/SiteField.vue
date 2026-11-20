<script setup>
import { CircleAlert } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    hint: { type: String, default: null },
    error: { type: String, default: null },
});

const control = computed(() => ({
    id: props.id,
    'aria-describedby': [props.hint && `${props.id}-hint`, props.error && `${props.id}-error`].filter(Boolean).join(' ') || undefined,
    'aria-invalid': props.error ? 'true' : undefined,
}));
</script>

<template>
    <div :class="['field', { 'field--invalid': error }]">
        <label class="field__label" :for="id">{{ label }}</label>
        <slot :control="control" />
        <p v-if="hint" :id="`${id}-hint`" class="field__hint">{{ hint }}</p>
        <p v-if="error" :id="`${id}-error`" class="field__error" data-test="field-error">
            <CircleAlert class="field__error-icon" aria-hidden="true" />
            <span>{{ error }}</span>
        </p>
    </div>
</template>

<style scoped>
.field {
    display: grid;
    gap: var(--space-2);
}

.field__label {
    font-family: var(--font-ui);
    font-weight: 700;
    line-height: 1.3;
}

.field :slotted(:is(input, textarea, select)) {
    width: 100%;
    min-height: var(--control-height);
    padding: var(--space-2) var(--space-3);
    font: inherit;
    font-size: var(--font-size);
    line-height: 1.4;
    color: var(--color-ink);
    background: var(--color-paper);
    border: var(--border-strong);
    border-radius: var(--radius-photo);
}

.field :slotted(textarea) {
    min-height: 12rem;
    resize: vertical;
}

.field :slotted(:is(input, textarea, select):focus-visible) {
    background: var(--color-card);
    outline-offset: 0.125rem;
}

.field--invalid :slotted(:is(input, textarea, select)) {
    background: var(--color-fur);
    border-width: 0.1875rem;
}

.field__hint {
    font-size: var(--font-size-xs);
    line-height: 1.4;
}

.field__error {
    display: flex;
    gap: var(--space-2);
    align-items: start;
    font-size: var(--font-size-sm);
    font-weight: 700;
    line-height: 1.35;
}

.field__error-icon {
    flex: none;
    width: 1.25rem;
    height: 1.25rem;
    margin-top: 0.0625rem;
    stroke-width: 2.25;
}
</style>
