<script setup>
import WashiTape from './WashiTape.vue';

defineProps({
    tag: { type: String, default: 'div' },
    tape: { type: String, default: null },
});
</script>

<template>
    <component :is="tag" class="paper-sheet">
        <WashiTape v-if="tape" class="paper-sheet__tape" :tone="tape" />
        <slot />
    </component>
</template>

<style scoped>
.paper-sheet {
    position: relative;
    display: grid;
    gap: var(--space-4);
    align-content: start;
    padding: var(--space-6) var(--space-5) var(--space-5);
    background: var(--color-card);
    border: 0.0625rem solid var(--color-edge);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-sheet);
}

.paper-sheet__tape {
    position: absolute;
    top: -0.9375rem;
    left: 50%;
    translate: -50% 0;
    rotate: -2deg;
}

@media (min-width: 40rem) {
    .paper-sheet {
        padding: var(--space-7) var(--space-6) var(--space-6);
    }
}
</style>
