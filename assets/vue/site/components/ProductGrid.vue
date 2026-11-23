<script setup>
import { computed } from 'vue';
import ProductCard from './ProductCard.vue';

const props = defineProps({
    products: { type: Array, required: true },
    eager: { type: Boolean, default: false },
    columns: { type: Number, default: 3 },
});

const sizes = computed(() => `(min-width: 56rem) ${Math.ceil(100 / props.columns)}vw, 50vw`);
const ratio = (product) => (product.image ? product.image.width / product.image.height : 0.8);
</script>

<template>
    <ul class="product-grid" data-products>
        <li v-for="(product, index) in products" :key="product.url" class="product-grid__item" :style="{ '--ratio': ratio(product) }">
            <ProductCard :product="product" :eager="eager && index === 0" :sizes="sizes" />
        </li>
    </ul>
</template>

<style scoped>
.product-grid {
    --row: 8rem;
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-5) 0.9375rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.product-grid::after {
    content: '';
    flex: 1e9 1 0;
}

.product-grid__item {
    flex: var(--ratio) 1 calc(var(--ratio) * var(--row));
    min-width: 0;
}

@media (min-width: 40rem) {
    .product-grid {
        --row: 13rem;
    }
}

@media (min-width: 56rem) {
    .product-grid {
        --row: 18rem;
        row-gap: var(--space-6);
    }
}
</style>
