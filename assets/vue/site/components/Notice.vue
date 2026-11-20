<script setup>
import { CircleAlert, CircleCheck } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({
    tone: { type: String, required: true, validator: (tone) => ['success', 'error'].includes(tone) },
    title: { type: String, default: null },
});

const icon = computed(() => ({ success: CircleCheck, error: CircleAlert })[props.tone]);
</script>

<template>
    <div :class="['notice', `notice--${tone}`]">
        <component :is="icon" class="notice__icon" aria-hidden="true" />
        <div class="notice__body">
            <p v-if="title" class="notice__title">{{ title }}</p>
            <slot />
        </div>
    </div>
</template>

<style scoped>
.notice {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: var(--space-3);
    align-items: start;
    padding: var(--space-4) var(--space-5) var(--space-4) var(--space-4);
    color: var(--color-ink);
    border: var(--border-strong);
    border-radius: var(--radius-photo);
}

.notice:focus {
    outline: none;
}

.notice:focus-visible {
    outline: 0.1875rem solid var(--color-ink);
}

.notice--success {
    background: var(--color-wash-leaf);
}

.notice--error {
    background: var(--color-fur);
}

.notice__icon {
    width: 1.5rem;
    height: 1.5rem;
    margin-top: 0.125rem;
    stroke-width: 2.25;
}

.notice__body {
    display: grid;
    gap: var(--space-1);
    font-size: var(--font-size-sm);
    line-height: 1.45;
}

.notice__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-lg);
    line-height: 1.15;
}
</style>
