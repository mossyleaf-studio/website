<script setup>
import ProductGrid from '../components/ProductGrid.vue';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <div class="category">
        <h1 class="category__title">{{ data.title }}</h1>
        <ProductGrid :products="data.products" eager />
    </div>
</template>

<style scoped>
.category {
    display: grid;
    gap: var(--space-7);
}

.category__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2.125rem, 1.5rem + 2.5vw, var(--font-size-3xl));
    line-height: 1.08;
    text-wrap: balance;
}
</style>
