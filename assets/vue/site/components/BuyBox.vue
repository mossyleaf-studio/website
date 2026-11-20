<script setup>
import { computed, inject, ref, useId, watch } from 'vue';
import { boundedQuantity, preselectedVariant } from '../buying.js';
import PillButton from './PillButton.vue';
import QuantityStepper from './QuantityStepper.vue';
import VariantPicker from './VariantPicker.vue';

const props = defineProps({
    variants: { type: Array, required: true },
});

const emit = defineEmits(['add']);

const cart = inject('cart', null);
const id = useId();
const sku = ref(preselectedVariant(props.variants)?.sku ?? '');
const quantity = ref(1);
const variant = computed(() => props.variants.find((entry) => entry.sku === sku.value) ?? null);
const available = computed(() => variant.value?.available ?? 0);
const state = computed(() => variant.value?.state ?? 'soldOut');
const picking = computed(() => props.variants.length > 1 || Boolean(props.variants[0]?.name));

watch(available, (max) => {
    quantity.value = boundedQuantity(quantity.value, max);
});
</script>

<template>
    <div class="buy-box" data-test="buy-box">
        <VariantPicker v-if="picking" v-model="sku" :variants="variants" :name="`${id}-variant`" />
        <p :class="['buy-box__stock', `buy-box__stock--${state}`]" aria-live="polite" data-stock data-test="stock-state">{{ $t(`product.${state}`) }}</p>
        <div v-if="state !== 'soldOut' || cart" class="buy-box__row">
            <QuantityStepper v-if="state !== 'soldOut'" :id="`${id}-quantity`" v-model="quantity" :max="available" />
            <PillButton v-if="cart" class="buy-box__add" :disabled="state === 'soldOut'" data-test="add-to-cart" @click="emit('add', { sku, quantity })">{{ $t('product.add') }}</PillButton>
        </div>
    </div>
</template>

<style scoped>
.buy-box {
    display: grid;
    gap: var(--space-5);
    justify-items: start;
}

.buy-box__stock {
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    font-weight: 700;
}

.buy-box__stock--lastPieces,
.buy-box__stock--soldOut {
    padding: 0 var(--space-2);
    background: var(--color-wash-star);
    border-radius: var(--radius-sm);
}

.buy-box__row {
    display: flex;
    flex-wrap: wrap;
    align-items: end;
    gap: var(--space-4);
}

.buy-box__add:disabled {
    color: var(--color-muted);
    background: var(--color-paper);
    border-style: dashed;
    cursor: not-allowed;
}
</style>
