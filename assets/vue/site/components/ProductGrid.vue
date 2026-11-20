<script setup>
import { computed } from 'vue';
import ProductCard from './ProductCard.vue';

const props = defineProps({
    products: { type: Array, required: true },
    eager: { type: Boolean, default: false },
    columns: { type: Number, default: 3 },
});

const sizes = computed(() => `(min-width: 56rem) ${Math.ceil(100 / props.columns)}vw, 50vw`);
</script>

<template>
    <ul :class="['product-grid', `product-grid--${columns}`]" data-products>
        <li v-for="(product, index) in products" :key="product.url">
            <ProductCard :product="product" :eager="eager && index === 0" :sizes="sizes" />
        </li>
    </ul>
</template>

<style scoped>
.product-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-6) var(--space-4);
    margin: 0;
    padding: 0;
    list-style: none;
}

@media (min-width: 40rem) {
    .product-grid {
        gap: var(--space-7) var(--grid-gap);
    }
}

@media (min-width: 56rem) {
    .product-grid--3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .product-grid--4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
</style>
