<script setup>
defineProps({
    categories: { type: Array, required: true },
});
</script>

<template>
    <ul class="category-tiles" data-tiles>
        <li v-for="category in categories" :key="category.key">
            <a class="category-tiles__tile" :href="category.url" data-test="category-tile">
                <img
                    v-if="category.image"
                    class="category-tiles__photo"
                    :src="category.image.src"
                    :srcset="category.image.srcset"
                    sizes="(min-width: 56rem) 25vw, 50vw"
                    alt=""
                    :width="category.image.width"
                    :height="category.image.height"
                    loading="lazy"
                />
                <span v-else class="category-tiles__photo category-tiles__photo--blank" />
                <span class="category-tiles__label">{{ category.label }}</span>
            </a>
        </li>
    </ul>
</template>

<style scoped>
.category-tiles {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 9rem), 1fr));
    gap: var(--space-5) var(--grid-gap);
    margin: 0;
    padding: 0;
    list-style: none;
}

.category-tiles__tile {
    display: grid;
    gap: var(--space-2);
    color: var(--color-ink);
    text-decoration: none;
    border-radius: var(--radius-photo);
}

.category-tiles__tile:focus-visible {
    outline-offset: 0.375rem;
}

.category-tiles__photo {
    width: 100%;
    height: auto;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    background: var(--color-fur);
    border: 0.0625rem solid var(--color-edge);
    border-radius: var(--radius-photo);
}

.category-tiles__photo--blank {
    display: block;
    background: var(--color-card);
}

.category-tiles__label {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-lg);
    line-height: 1.2;
    text-underline-offset: 0.2em;
}

.category-tiles__tile:hover .category-tiles__label {
    text-decoration-line: underline;
}
</style>
