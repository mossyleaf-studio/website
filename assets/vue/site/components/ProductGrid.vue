<script setup>
import ProductCard from './ProductCard.vue';

defineProps({
    products: { type: Array, required: true },
    eager: { type: Boolean, default: false },
});
</script>

<template>
    <ul class="product-grid" data-products>
        <li v-for="(product, index) in products" :key="product.url" class="product-grid__item">
            <ProductCard :product="product" :eager="eager && index === 0" />
        </li>
    </ul>
</template>

<style scoped>
.product-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-5) 0.9375rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.product-grid__item {
    display: grid;
    min-width: 0;
}

@media (min-width: 40rem) {
    .product-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (min-width: 56rem) {
    .product-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
        row-gap: var(--space-6);
    }
}
</style>
