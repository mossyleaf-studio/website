<script setup>
import WashiTape from './WashiTape.vue';

defineProps({
    href: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, required: true },
    tape: { type: String, default: 'leaf' },
    rel: { type: String, default: null },
});
</script>

<template>
    <a class="card" :href="href" :rel="rel ?? undefined">
        <WashiTape class="card__tape" :tone="tape" />
        <span class="card__title">{{ title }}</span>
        <span class="card__description">{{ description }}</span>
    </a>
</template>

<style scoped>
.card {
    position: relative;
    display: grid;
    gap: var(--space-1);
    padding: var(--space-6) var(--space-5) var(--space-5);
    text-decoration: none;
    color: var(--color-text);
    background: var(--color-card);
    border: 0.0625rem solid var(--color-line);
    border-radius: 0.1875rem;
    box-shadow: 0 0.0625rem 0.125rem var(--color-shadow), 0 0.75rem 1.5rem -0.75rem var(--color-shadow);
    transition: translate var(--transition), box-shadow var(--transition);
}

.card:hover {
    translate: 0 -0.125rem;
    box-shadow: 0 0.0625rem 0.125rem var(--color-shadow), 0 1rem 1.75rem -0.75rem var(--color-shadow);
}

.card__tape {
    position: absolute;
    top: -0.75rem;
    left: var(--space-5);
    rotate: -3deg;
}

.card__title {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: var(--font-size-xl);
    line-height: 1.1;
    color: var(--color-ink);
    filter: url(#graphite);
}

.card__description {
    font-size: var(--font-size-sm);
    font-weight: 400;
    line-height: 1.45;
    color: var(--color-muted);
}

@media (prefers-reduced-motion: reduce) {
    .card {
        transition: none;
    }

    .card:hover {
        translate: none;
    }
}
</style>
