<script setup>
import { X } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatPrice } from '../money.js';
import QuantityStepper from './QuantityStepper.vue';

const props = defineProps({
    line: { type: Object, required: true },
    id: { type: String, required: true },
});

const emit = defineEmits(['quantity', 'remove']);

const { locale } = useI18n();
const product = computed(() => [props.line.title, props.line.variant].filter(Boolean).join(', '));
const quantity = computed({
    get: () => props.line.quantity,
    set: (value) => emit('quantity', value),
});
</script>

<template>
    <li class="cart-line" data-test="cart-line">
        <a class="cart-line__photo" :href="line.url" tabindex="-1" aria-hidden="true">
            <img v-if="line.image" :src="line.image.src" :srcset="line.image.srcset" sizes="6rem" :width="line.image.width" :height="line.image.height" alt="" loading="lazy" />
        </a>
        <div class="cart-line__product">
            <h2 class="cart-line__title"><a :href="line.url">{{ line.title }}</a></h2>
            <p v-if="line.variant" class="cart-line__variant">{{ line.variant }}</p>
            <p class="cart-line__unit">{{ $t('cart.each', { price: formatPrice(line.unitPrice, locale) }) }}</p>
        </div>
        <QuantityStepper :id="id" v-model="quantity" class="cart-line__quantity" :max="line.available" :product="product" />
        <p class="cart-line__total" data-test="line-total">{{ formatPrice(line.lineTotal, locale) }}</p>
        <button class="cart-line__remove" type="button" :aria-label="$t('cart.removeProduct', { product })" data-test="remove-line" @click="emit('remove')">
            <X class="cart-line__remove-icon" aria-hidden="true" />
            <span>{{ $t('cart.remove') }}</span>
        </button>
    </li>
</template>

<style scoped>
.cart-line {
    display: grid;
    grid-template-columns: 5rem minmax(0, 1fr) auto;
    grid-template-areas:
        'photo product total'
        'photo quantity remove';
    gap: var(--space-3) var(--space-4);
    align-items: center;
    padding-block: var(--space-5);
    border-bottom: 0.0625rem solid var(--color-edge);
}

.cart-line__photo {
    grid-area: photo;
    align-self: start;
    display: block;
    aspect-ratio: 1;
    overflow: hidden;
    background: var(--color-wash-leaf);
    border: 0.0625rem solid var(--color-edge);
    border-radius: var(--radius-photo);
}

.cart-line__photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cart-line__product {
    grid-area: product;
    display: grid;
    gap: var(--space-1);
}

.cart-line__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-lg);
    line-height: 1.15;
}

.cart-line__title a {
    text-decoration: none;
}

.cart-line__title a:hover {
    text-decoration: underline;
}

.cart-line__variant,
.cart-line__unit {
    font-size: var(--font-size-xs);
    line-height: 1.35;
}

.cart-line__unit {
    color: var(--color-muted);
    font-variant-numeric: tabular-nums;
}

.cart-line__quantity {
    grid-area: quantity;
}

.cart-line__quantity :deep(.quantity-stepper__label) {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
}

.cart-line__total {
    grid-area: total;
    align-self: start;
    justify-self: end;
    font-family: var(--font-ui);
    font-size: var(--font-size);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.cart-line__remove {
    grid-area: remove;
    justify-self: end;
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    min-height: var(--tap);
    padding: 0 var(--space-3) 0 var(--space-2);
    font-family: var(--font-ui);
    font-size: var(--font-size-xs);
    font-weight: 700;
    color: var(--color-ink);
    background: transparent;
    border: 0.0625rem solid var(--color-edge);
    border-radius: var(--radius-pill);
    cursor: pointer;
}

.cart-line__remove:hover {
    border-color: var(--color-ink);
    background: var(--color-fur);
}

.cart-line__remove-icon {
    width: 1.125rem;
    height: 1.125rem;
    stroke-width: 2.25;
}

@media (min-width: 40rem) {
    .cart-line {
        grid-template-columns: 6rem minmax(0, 1fr) auto 6rem auto;
        grid-template-areas: 'photo product quantity total remove';
        gap: var(--space-5);
    }

    .cart-line__total {
        align-self: center;
    }
}
</style>
