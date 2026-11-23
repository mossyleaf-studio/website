<script setup>
defineProps({
    categories: { type: Array, required: true },
});

const ratio = (category) => (category.image ? category.image.width / category.image.height : 0.8);
</script>

<template>
    <ul class="category-tiles" data-tiles>
        <li v-for="category in categories" :key="category.key" class="category-tiles__item" :style="{ '--ratio': ratio(category) }">
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
    --row: 8rem;
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-5) 0.9375rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.category-tiles::after {
    content: '';
    flex: 1e9 1 0;
}

.category-tiles__item {
    flex: var(--ratio) 1 calc(var(--ratio) * var(--row));
    min-width: 0;
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
    border-radius: var(--radius-photo);
}

.category-tiles__photo--blank {
    display: block;
    aspect-ratio: 4 / 5;
    background: var(--color-meadow);
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

@media (min-width: 40rem) {
    .category-tiles {
        --row: 11rem;
    }
}

@media (min-width: 56rem) {
    .category-tiles {
        --row: 14rem;
    }
}
</style>
