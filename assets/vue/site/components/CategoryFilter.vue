<script setup>
defineProps({
    categories: { type: Array, required: true },
});
</script>

<template>
    <nav class="category-filter" :aria-label="$t('shop.categories')" data-categories data-test="category-filter">
        <ul class="category-filter__list">
            <li v-for="category in categories" :key="category.key ?? ''">
                <a :class="['category-filter__chip', { 'category-filter__chip--current': category.current }]" :href="category.url" :aria-current="category.current ? 'page' : undefined">{{ category.label }}</a>
            </li>
        </ul>
    </nav>
</template>

<style scoped>
.category-filter__list {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2) var(--space-3);
    margin: 0;
    padding: 0;
    list-style: none;
}

.category-filter__chip {
    display: inline-flex;
    align-items: center;
    min-height: var(--tap);
    padding: 0 var(--space-4);
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    color: var(--color-ink);
    text-decoration: none;
    background: var(--color-card);
    border: 0.0625rem solid var(--color-ink);
    border-radius: var(--radius-pill);
}

.category-filter__chip:hover {
    background: var(--color-wash-leaf);
}

.category-filter__chip--current {
    font-weight: 600;
    background: var(--color-leaf);
    border: var(--border-strong);
}

.category-filter__chip--current:hover {
    background: var(--color-leaf);
}
</style>
