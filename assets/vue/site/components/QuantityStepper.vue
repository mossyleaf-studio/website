<script setup>
import { Minus, Plus } from '@lucide/vue';
import { boundedQuantity } from '../buying.js';

const props = defineProps({
    max: { type: Number, required: true },
    id: { type: String, required: true },
    product: { type: String, default: null },
});

const quantity = defineModel({ type: Number, required: true });

const set = (value) => {
    quantity.value = boundedQuantity(value, props.max);
};

const typed = (event) => {
    set(Number(event.target.value));
    event.target.value = quantity.value;
};
</script>

<template>
    <div class="quantity-stepper" data-test="quantity-stepper">
        <label class="quantity-stepper__label" :for="id">{{ product ? $t('product.quantityOf', { product }) : $t('product.quantity') }}</label>
        <div class="quantity-stepper__controls">
            <button class="quantity-stepper__step" type="button" :aria-label="product ? $t('product.lessOf', { product }) : $t('product.less')" :aria-controls="id" :aria-disabled="quantity <= 1" @click="set(quantity - 1)">
                <Minus :size="18" aria-hidden="true" />
            </button>
            <input :id="id" class="quantity-stepper__input" type="number" inputmode="numeric" min="1" :max="max" :value="quantity" @change="typed" />
            <button class="quantity-stepper__step" type="button" :aria-label="product ? $t('product.moreOf', { product }) : $t('product.more')" :aria-controls="id" :aria-disabled="quantity >= max" @click="set(quantity + 1)">
                <Plus :size="18" aria-hidden="true" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.quantity-stepper {
    display: grid;
    gap: var(--space-2);
    justify-items: start;
}

.quantity-stepper__label {
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    font-weight: 700;
}

.quantity-stepper__controls {
    display: inline-flex;
    align-items: stretch;
    border: var(--border-strong);
    border-radius: var(--radius-pill);
    background: var(--color-card);
}

.quantity-stepper__step {
    display: grid;
    place-items: center;
    width: var(--tap);
    min-height: var(--control-height);
    padding: 0;
    color: var(--color-ink);
    background: transparent;
    border: 0;
    border-radius: var(--radius-pill);
    cursor: pointer;
}

.quantity-stepper__step:hover:not([aria-disabled='true']) {
    background: var(--color-wash-leaf);
}

.quantity-stepper__step[aria-disabled='true'] {
    color: var(--color-line);
    cursor: not-allowed;
}

.quantity-stepper__input {
    width: 3rem;
    padding: 0;
    font-family: var(--font-ui);
    font-size: var(--font-size);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--color-ink);
    text-align: center;
    background: transparent;
    border: 0;
    border-inline: 0.0625rem solid var(--color-edge);
    appearance: textfield;
}

.quantity-stepper__input::-webkit-inner-spin-button,
.quantity-stepper__input::-webkit-outer-spin-button {
    appearance: none;
    margin: 0;
}
</style>
