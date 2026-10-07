<script setup>
import WashiTape from './WashiTape.vue';

const heroes = import.meta.glob('../art/hero.{jpg,jpeg,png,webp,avif}', { eager: true, import: 'default' });
const hero = Object.values(heroes)[0] ?? null;

defineProps({
    alt: { type: String, default: 'A drawing by mossyleaf.studio' },
});
</script>

<template>
    <figure v-if="hero" class="featured">
        <WashiTape class="featured__tape featured__tape--left" tone="leaf" />
        <WashiTape class="featured__tape featured__tape--right" tone="blossom" />
        <img class="featured__art" :src="hero" :alt="alt" width="960" height="720" />
    </figure>
</template>

<style scoped>
.featured {
    position: relative;
    padding: var(--space-3);
    background: var(--color-card);
    border: 0.0625rem solid var(--color-line);
    box-shadow: 0 0.0625rem 0.125rem var(--color-shadow), 0 1rem 2rem -1rem var(--color-shadow);
}

.featured__art {
    width: 100%;
    height: auto;
}

.featured__tape {
    position: absolute;
    top: -0.5rem;
    z-index: 1;
}

.featured__tape--left {
    left: -1.25rem;
    rotate: -35deg;
}

.featured__tape--right {
    right: -1.25rem;
    rotate: 35deg;
}
</style>
