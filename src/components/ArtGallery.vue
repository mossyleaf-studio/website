<script setup>
import WashiTape from './WashiTape.vue';

const files = import.meta.glob('../art/gallery/*.{jpg,jpeg,png,webp,avif}', { eager: true, import: 'default' });

const pieces = Object.entries(files)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([path, src]) => {
        const name = path.split('/').pop().replace(/\.[^.]+$/, '').replace(/^\d+[-_ ]*/, '').replace(/[-_]+/g, ' ');

        return { src, alt: name.charAt(0).toUpperCase() + name.slice(1) };
    });
</script>

<template>
    <section v-if="pieces.length" class="gallery" aria-labelledby="gallery-title">
        <h2 id="gallery-title" class="gallery__title">Recent drawings</h2>
        <ul class="gallery__grid">
            <li v-for="(piece, index) in pieces" :key="piece.src" class="gallery__print">
                <WashiTape class="gallery__tape" :tone="index % 2 ? 'blossom' : 'leaf'" />
                <img class="gallery__art" :src="piece.src" :alt="piece.alt" loading="lazy" width="480" height="600" />
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
