<script setup>
defineProps({
    categories: { type: Array, required: true },
});
</script>

<template>
    <ul class="category-tiles" data-tiles>
        <li v-for="category in categories" :key="category.key" class="category-tiles__item">
            <a class="category-tiles__tile" :href="category.url" data-test="category-tile">
                <img
                    v-if="category.image"
                    class="category-tiles__photo"
                    :src="category.image.src"
                    :srcset="category.image.srcset"
                    sizes="(min-width: 87.5rem) 16rem, (min-width: 56rem) 20vw, (min-width: 40rem) 33vw, 50vw"
                    alt=""
                    :width="category.image.width"
                    :height="category.image.height"
                    loading="lazy"
                />
                <span v-else class="category-tiles__photo" />
                <span class="category-tiles__label">{{ category.label }}</span>
            </a>
        </li>
    </ul>
</template>

<style scoped>
.category-tiles {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-5) 0.9375rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.category-tiles__item {
    display: grid;
    min-width: 0;
}

.category-tiles__tile {
    display: grid;
    grid-template-rows: auto 1fr;
    gap: var(--space-2);
    color: var(--color-ink);
    text-decoration: none;
    border-radius: var(--radius-photo);
}

.category-tiles__tile:focus-visible {
    outline-offset: 0.375rem;
}

.category-tiles__photo {
    display: block;
    width: 100%;
    height: auto;
    aspect-ratio: 1;
    object-fit: cover;
    object-position: center;
    background: var(--color-meadow);
    border-radius: var(--radius-photo);
}

.category-tiles__label {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-lg);
    line-height: 1.2;
    overflow-wrap: anywhere;
    text-underline-offset: 0.2em;
}

.category-tiles__tile:hover .category-tiles__label {
    text-decoration-line: underline;
}

@media (min-width: 40rem) {
    .category-tiles {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (min-width: 56rem) {
    .category-tiles {
        grid-template-columns: repeat(5, minmax(0, 1fr));
        row-gap: var(--space-6);
    }
}
</style>
