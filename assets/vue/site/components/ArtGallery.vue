<script setup>
import WashiTape from './WashiTape.vue';

defineProps({
    title: { type: String, required: true },
    artworks: { type: Array, required: true },
});
</script>

<template>
    <section class="gallery" aria-labelledby="gallery-title">
        <h2 id="gallery-title" class="gallery__title">{{ title }}</h2>
        <ul class="gallery__grid">
            <li v-for="(artwork, index) in artworks" :key="artwork.id" class="gallery__print">
                <WashiTape class="gallery__tape" :tone="index % 2 ? 'blossom' : 'leaf'" />
                <img class="gallery__art" :src="artwork.url" :alt="artwork.alt" loading="lazy" :width="artwork.width" :height="artwork.height" />
            </li>
        </ul>
    </section>
</template>

<style scoped>
.gallery {
    display: grid;
    gap: var(--space-5);
}

.gallery__title {
    font-family: var(--font-display);
    font-weight: 400;
    font-size: var(--font-size-xl);
    color: var(--color-leaf-deep);
}

.gallery__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr));
    gap: var(--space-6) var(--space-4);
    margin: 0;
    padding: 0;
    list-style: none;
}

.gallery__print {
    position: relative;
    padding: var(--space-2);
    background: var(--color-card);
    border: 0.0625rem solid var(--color-line);
    box-shadow: 0 0.0625rem 0.125rem var(--color-shadow), 0 0.75rem 1.25rem -0.75rem var(--color-shadow);
}

.gallery__tape {
    position: absolute;
    top: -0.625rem;
    left: 50%;
    width: 3.5rem;
    height: 1.125rem;
    translate: -50% 0;
    rotate: -2deg;
}

.gallery__art {
    width: 100%;
    height: auto;
    aspect-ratio: 4 / 5;
    object-fit: cover;
}
</style>
