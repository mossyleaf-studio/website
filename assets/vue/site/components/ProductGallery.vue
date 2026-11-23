<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    photos: { type: Array, required: true },
});

const shown = ref(0);
const cover = computed(() => props.photos[shown.value] ?? null);
const tallest = computed(() => (props.photos.length ? Math.min(...props.photos.map((photo) => photo.width / photo.height)) : null));
</script>

<template>
    <section class="product-gallery" :aria-label="$t('product.photos')" data-test="product-gallery">
        <div class="product-gallery__frame" :style="tallest ? { '--tallest': tallest } : undefined">
            <img
                v-if="cover"
                :key="cover.src"
                class="product-gallery__cover"
                :src="cover.src"
                :srcset="cover.srcset"
                sizes="(min-width: 56rem) 50vw, 100vw"
                :alt="cover.alt"
                :width="cover.width"
                :height="cover.height"
                fetchpriority="high"
            />
        </div>
        <ul v-if="photos.length > 1" class="product-gallery__thumbs">
            <li v-for="(photo, index) in photos" :key="photo.src">
                <button
                    :class="['product-gallery__thumb', { 'product-gallery__thumb--shown': index === shown }]"
                    type="button"
                    :aria-pressed="index === shown"
                    :aria-label="$t('product.photo', { number: index + 1, count: photos.length })"
                    data-test="gallery-thumb"
                    @click="shown = index"
                >
                    <img class="product-gallery__thumb-photo" :src="photo.src" alt="" :width="photo.width" :height="photo.height" loading="lazy" />
                </button>
            </li>
        </ul>
    </section>
</template>

<style scoped>
.product-gallery {
    container-type: inline-size;
    display: grid;
    gap: var(--space-4);
    align-content: start;
}

.product-gallery__frame {
    height: min(80vh, 100cqi / var(--tallest));
}

.product-gallery__cover {
    width: auto;
    max-width: 100%;
    height: auto;
    max-height: 100%;
    border-radius: var(--radius-photo);
}

.product-gallery__thumbs {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-3);
    margin: 0;
    padding: 0;
    list-style: none;
}

.product-gallery__thumb {
    display: block;
    width: 4.5rem;
    padding: 0.1875rem;
    background: transparent;
    border: 0.0625rem solid var(--color-edge);
    border-radius: var(--radius-photo);
    cursor: pointer;
}

.product-gallery__thumb:hover {
    border-color: var(--color-ink);
}

.product-gallery__thumb--shown {
    border: var(--border-strong);
    padding: 0.125rem;
}

.product-gallery__thumb-photo {
    width: 100%;
    height: auto;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: 0.1875rem;
}
</style>
