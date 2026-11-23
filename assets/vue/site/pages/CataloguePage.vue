<script setup>
import FilterChips from '../components/FilterChips.vue';
import ProductGrid from '../components/ProductGrid.vue';
import TapeLabel from '../components/TapeLabel.vue';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <div class="catalogue">
        <div class="catalogue__head">
            <p v-if="data.collection?.hidden" class="catalogue__hidden" data-hidden>
                <TapeLabel tone="leaf">{{ $t('product.hidden') }}</TapeLabel>
            </p>
            <h1 class="catalogue__title">{{ data.title }}</h1>
            <FilterChips v-if="data.filters" :filters="data.filters" />
        </div>
        <ProductGrid :products="data.products" eager />
    </div>
</template>

<style scoped>
.catalogue {
    display: grid;
    gap: var(--space-7);
}

.catalogue__head {
    display: grid;
    justify-items: start;
    gap: var(--space-5);
}

.catalogue__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2.125rem, 1.5rem + 2.5vw, var(--font-size-3xl));
    line-height: 1.08;
    text-wrap: balance;
}

.catalogue__hidden {
    margin-bottom: calc(-1 * var(--space-3));
}
</style>
