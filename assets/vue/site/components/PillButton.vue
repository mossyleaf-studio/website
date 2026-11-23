<script setup>
defineProps({
    href: { type: String, default: null },
    tone: { type: String, default: 'meadow' },
});
</script>

<template>
    <component :is="href ? 'a' : 'button'" :class="['pill', `pill--${tone}`]" :href="href ?? undefined" :type="href ? undefined : 'button'">
        <slot />
    </component>
</template>

<style scoped>
.pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    min-height: var(--control-height);
    padding: 0 var(--space-5);
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    font-weight: 600;
    line-height: 1.2;
    color: var(--color-ink);
    text-decoration: none;
    border: var(--border-strong);
    border-radius: var(--radius-pill);
    cursor: pointer;
    transition: background-color var(--transition);
}

.pill:disabled {
    cursor: progress;
    opacity: 0.7;
}

.pill--meadow {
    background: var(--color-meadow);
}

.pill--meadow:hover {
    background: color-mix(in oklch, var(--color-meadow) 60%, var(--color-paper));
}

.pill--paper {
    background: var(--color-paper);
}

.pill--paper:hover {
    background: var(--color-wash-leaf);
}
</style>
