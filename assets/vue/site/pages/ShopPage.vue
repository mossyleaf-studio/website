<script setup>
import CategoryFilter from '../components/CategoryFilter.vue';
import PaperSheet from '../components/PaperSheet.vue';
import ProductGrid from '../components/ProductGrid.vue';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <div class="shop">
        <header class="shop__header">
            <h1 class="shop__title">{{ data.title }}</h1>
            <CategoryFilter v-if="data.categories.length > 1" :categories="data.categories" />
        </header>
        <ProductGrid v-if="data.products.length" :products="data.products" eager />
        <PaperSheet v-else class="shop__empty" data-empty>
            <p>{{ $t('shop.empty') }}</p>
        </PaperSheet>
    </div>
</template>

<style scoped>
.shop {
    display: grid;
    gap: var(--space-7);
}

.shop__header {
    display: grid;
    gap: var(--space-5);
}

.shop__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2.125rem, 1.5rem + 2.5vw, var(--font-size-3xl));
    line-height: 1.08;
}

.shop__empty {
    width: min(100%, 32rem);
}
</style>
