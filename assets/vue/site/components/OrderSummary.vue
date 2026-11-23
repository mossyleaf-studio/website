<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatPrice } from '../money.js';
import { missingForFree, shippingState } from '../shipping.js';
import PaperSheet from './PaperSheet.vue';
import PillButton from './PillButton.vue';
import SiteField from './SiteField.vue';

const props = defineProps({
    quote: { type: Object, required: true },
    countries: { type: Array, required: true },
    checkoutUrl: { type: String, default: null },
    busy: { type: Boolean, default: false },
    chooseCountry: { type: Boolean, default: true },
});

const country = defineModel('country', { type: String, default: null });

const { locale } = useI18n();
const price = (cents) => formatPrice(cents, locale.value);
const state = computed(() => shippingState(props.quote));
const missing = computed(() => missingForFree(props.quote));
const note = computed(() => {
    if (!props.countries.length) return 'nowhere';
    return state.value === 'choose' ? 'chooseFirst' : 'undeliverable';
});
</script>

<template>
    <PaperSheet tag="section" tape="leaf" class="order-summary" aria-labelledby="order-summary-title" :aria-busy="busy ? 'true' : undefined" data-test="order-summary">
        <h2 id="order-summary-title" class="order-summary__title">{{ $t('cart.summary.title') }}</h2>
        <slot />
        <SiteField v-if="chooseCountry && countries.length" v-slot="{ control }" id="cart-country" :label="$t('cart.summary.country')">
            <select v-bind="control" v-model="country" autocomplete="country" data-test="cart-country">
                <option v-if="country === null" :value="null" disabled>{{ $t('cart.summary.chooseCountry') }}</option>
                <option v-for="entry in countries" :key="entry.code" :value="entry.code">{{ entry.name }}</option>
            </select>
        </SiteField>

        <dl :class="['order-summary__amounts', { 'order-summary__amounts--busy': busy }]">
            <div class="order-summary__row">
                <dt>{{ $t('cart.summary.goods') }}</dt>
                <dd data-test="summary-goods">{{ price(quote.goods) }}</dd>
            </div>
            <div class="order-summary__row" data-test="summary-shipping">
                <dt>{{ $t('cart.summary.shipping') }}</dt>
                <dd v-if="state === 'priced'">{{ price(quote.shipping.price) }}</dd>
                <dd v-else-if="state === 'free'">{{ $t('cart.summary.free') }}</dd>
                <dd v-else class="order-summary__note" data-test="shipping-note">{{ $t(`cart.summary.${note}`) }}</dd>
                <dd v-if="missing !== null" class="order-summary__missing" data-test="missing-for-free">{{ $t('cart.summary.missing', { amount: price(missing) }) }}</dd>
            </div>
            <div class="order-summary__row order-summary__row--total">
                <dt>{{ $t('cart.summary.total') }}</dt>
                <dd data-test="summary-total">{{ price(quote.total) }}</dd>
            </div>
        </dl>

        <PillButton v-if="checkoutUrl && quote.deliverable" class="order-summary__checkout" :href="checkoutUrl" data-turbo-prefetch="false" data-test="checkout">{{ $t('cart.summary.checkout') }}</PillButton>
    </PaperSheet>
</template>

<style scoped>
.order-summary__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-xl);
    line-height: 1.1;
}

.order-summary__amounts {
    display: grid;
    gap: var(--space-2);
    transition: opacity var(--transition);
}

.order-summary__amounts--busy {
    opacity: 0.6;
}

.order-summary__row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: var(--space-2) var(--space-4);
    align-items: baseline;
    font-family: var(--font-ui);
}

.order-summary__row dd {
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    text-align: end;
}

.order-summary__row--total {
    margin-top: var(--space-2);
    padding-top: var(--space-3);
    font-size: var(--font-size-lg);
    border-top: var(--border-strong);
}

.order-summary__row .order-summary__note,
.order-summary__row .order-summary__missing {
    grid-column: 1 / -1;
    font-family: var(--font-body);
    font-size: var(--font-size-xs);
    font-weight: 400;
    line-height: 1.4;
    text-align: start;
}

.order-summary__row .order-summary__missing {
    justify-self: start;
    padding: 0 var(--space-2);
    background: var(--color-wash-leaf);
    border-radius: var(--radius-sm);
}

.order-summary__checkout {
    width: 100%;
}

@media (prefers-reduced-motion: reduce) {
    .order-summary__amounts {
        transition: none;
    }
}
</style>
