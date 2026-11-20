<script setup>
defineProps({
    href: { type: String, default: null },
    tone: { type: String, default: 'leaf' },
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
    font-weight: 700;
    line-height: 1.2;
    color: var(--color-ink);
    text-decoration: none;
    border: var(--border-strong);
    border-radius: var(--radius-pill);
    box-shadow: 0 0.1875rem 0 var(--color-ink);
    cursor: pointer;
    transition: translate var(--transition), box-shadow var(--transition);
}

.pill:hover {
    translate: 0 0.0625rem;
    box-shadow: 0 0.125rem 0 var(--color-ink);
}

.pill:active {
    translate: 0 0.1875rem;
    box-shadow: none;
}

.pill--leaf {
    background: var(--color-leaf);
}

.pill--paper {
    background: var(--color-paper);
}

@media (prefers-reduced-motion: reduce) {
    .pill {
        transition: none;
    }
}
</style>
