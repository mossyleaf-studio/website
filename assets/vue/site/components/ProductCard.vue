<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatPrice } from '../money.js';
import TapeLabel from './TapeLabel.vue';

const props = defineProps({
    product: { type: Object, required: true },
    eager: { type: Boolean, default: false },
    sizes: { type: String, default: '(min-width: 56rem) 33vw, 50vw' },
});

const { locale } = useI18n();
const price = computed(() => formatPrice(props.product.price, locale.value));
</script>

<template>
    <a class="product-card" :href="product.url" data-test="product-card">
        <img
            v-if="product.image"
            class="product-card__photo"
            :src="product.image.src"
            :srcset="product.image.srcset"
            :sizes="sizes"
            :alt="product.image.alt"
            :width="product.image.width"
            :height="product.image.height"
            :loading="eager ? 'eager' : 'lazy'"
            :fetchpriority="eager ? 'high' : undefined"
        />
        <span v-else class="product-card__photo product-card__photo--blank" />
        <span class="product-card__title" data-card-title>{{ product.title }}</span>
        <span class="product-card__price" data-card-price>{{ price }}</span>
        <span v-if="product.soldOut || product.hidden" class="product-card__labels">
            <TapeLabel v-if="product.soldOut" tone="blossom">{{ $t('product.soldOut') }}</TapeLabel>
            <TapeLabel v-if="product.hidden" tone="leaf">{{ $t('product.hidden') }}</TapeLabel>
        </span>
    </a>
</template>

<style scoped>
.product-card {
    position: relative;
    display: grid;
    gap: var(--space-1);
    color: var(--color-ink);
    text-decoration: none;
    border-radius: var(--radius-photo);
}

.product-card:focus-visible {
    outline-offset: 0.375rem;
}

.product-card__photo {
    width: 100%;
    height: auto;
    margin-bottom: var(--space-2);
    border-radius: var(--radius-photo);
}

.product-card__photo--blank {
    display: block;
    aspect-ratio: 4 / 5;
    background: var(--color-meadow);
}

.product-card__title {
    overflow: hidden;
    font-size: var(--font-size);
    line-height: 1.35;
    white-space: nowrap;
    text-overflow: ellipsis;
    text-decoration-thickness: 0.0625rem;
    text-underline-offset: 0.2em;
}

.product-card:hover .product-card__title {
    text-decoration-line: underline;
}

.product-card__price {
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.product-card__labels {
    position: absolute;
    top: -0.5rem;
    left: -0.5rem;
    display: grid;
    justify-items: start;
    gap: var(--space-1);
}
</style>
