<script setup>
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError } from '../../composables/apiError.js';
import { postJson } from '../api.js';
import { issueNames, pricedLines } from '../cart.js';
import { chosenCountry } from '../shipping.js';
import CartLine from '../components/CartLine.vue';
import Notice from '../components/Notice.vue';
import OrderSummary from '../components/OrderSummary.vue';
import PillButton from '../components/PillButton.vue';

const props = defineProps({
    data: { type: Object, required: true },
});

const DEBOUNCE = 300;

const { t, locale } = useI18n();
const cart = inject('cart');
const quote = ref(null);
const issues = ref([]);
const failure = ref(null);
const pending = ref(false);
const heading = ref(null);
let timer = null;
let priced = null;

const stored = computed(() => cart.cart.value);
const country = computed({
    get: () => chosenCountry(stored.value.country, props.data.countries, props.data.defaultCountry),
    set: (code) => cart.setCountry(code),
});
const request = computed(() => JSON.stringify({ lines: stored.value.lines.map(({ sku, quantity }) => ({ sku, quantity })), country: country.value }));
const empty = computed(() => stored.value.lines.length === 0);
const lines = computed(() => (quote.value ? pricedLines(stored.value, quote.value) : []));
const units = computed(() => cart.units.value);

const listed = (type) => {
    const { titles, unnamed } = issueNames(issues.value, type);
    const names = unnamed ? [...titles, t('cart.issues.unnamed', { count: unnamed }, unnamed)] : titles;
    return names.length ? t(`cart.issues.${type}`, { products: new Intl.ListFormat(locale.value, { type: 'conjunction' }).format(names) }) : null;
};
const notices = computed(() => ['removed', 'reduced'].map(listed).filter(Boolean));

async function ask() {
    clearTimeout(timer);
    const sent = request.value;
    failure.value = null;
    if (empty.value) {
        pending.value = false;
        issues.value = [];
        return;
    }

    pending.value = true;
    try {
        const answer = await postJson(props.data.quoteUrl, JSON.parse(sent));
        if (sent !== request.value) return;
        quote.value = answer;
        issues.value = answer.issues;
        cart.settle(answer);
        priced = request.value;
    } catch (error) {
        if (sent !== request.value) return;
        failure.value = error instanceof ApiError && error.status === 429 ? 'tooMany' : 'other';
    }
    pending.value = false;
}

watch(request, (current) => {
    clearTimeout(timer);
    pending.value = current !== priced;
    if (pending.value) timer = setTimeout(ask, DEBOUNCE);
    else failure.value = null;
});

onMounted(ask);
onBeforeUnmount(() => clearTimeout(timer));

async function remove(sku) {
    cart.remove(sku);
    await nextTick();
    heading.value?.focus();
}
</script>

<template>
    <article class="cart" data-test="cart">
        <header class="cart__header">
            <h1 ref="heading" class="cart__title" tabindex="-1">{{ data.title }}</h1>
            <p v-if="!empty" class="cart__units" data-test="cart-units">{{ $t('cart.units', { count: units }, units) }}</p>
        </header>

        <div class="cart__notice" role="status">
            <Notice v-if="notices.length" tone="error" :title="$t('cart.issues.title')" data-test="cart-issues">
                <p v-for="text in notices" :key="text">{{ text }}</p>
            </Notice>
        </div>

        <Notice v-if="failure" class="cart__notice" tone="error" role="alert" data-test="cart-failure">
            <p>{{ $t(`cart.failure.${failure}`) }}</p>
            <PillButton class="cart__retry" tone="paper" data-test="cart-retry" @click="ask">{{ $t('cart.failure.retry') }}</PillButton>
        </Notice>

        <div v-if="empty" class="cart__empty" data-test="cart-empty">
            <p class="cart__empty-text">{{ $t('cart.empty') }}</p>
            <PillButton :href="data.shopUrl" data-shop>{{ $t('cart.backToShop') }}</PillButton>
        </div>

        <div v-else-if="quote" class="cart__body">
            <ul class="cart__lines" :aria-label="$t('cart.lines')">
                <CartLine v-for="(line, index) in lines" :id="`cart-quantity-${index}`" :key="line.sku" :line="line" @quantity="cart.setQuantity(line.sku, $event)" @remove="remove(line.sku)" />
            </ul>
            <OrderSummary v-model:country="country" class="cart__summary" :quote="quote" :countries="data.countries" :checkout-url="data.checkoutUrl" :busy="pending || failure !== null" />
            <p class="cart__more"><a :href="data.shopUrl" data-shop>{{ $t('cart.backToShop') }}</a></p>
        </div>

        <div v-else-if="!failure" class="cart__loading" aria-busy="true" data-test="cart-loading">
            <span class="visually-hidden">{{ $t('cart.loading') }}</span>
            <span v-for="row in Math.min(stored.lines.length, 3)" :key="row" class="cart__placeholder" aria-hidden="true" />
        </div>
    </article>
</template>

<style scoped>
.cart {
    display: grid;
    gap: var(--space-6);
}

.cart__header {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--space-2) var(--space-4);
}

.cart__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2.125rem, 1.5rem + 2.5vw, var(--font-size-3xl));
    line-height: 1.08;
}

.cart__title:focus {
    outline: none;
}

.cart__title:focus-visible {
    outline: 0.1875rem solid var(--color-ink);
}

.cart__units {
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    color: var(--color-muted);
}

.cart__notice {
    max-width: 44rem;
}

.cart__notice:empty {
    margin-top: calc(-1 * var(--space-6));
}

.cart__retry {
    justify-self: start;
    margin-top: var(--space-2);
}

.cart__empty {
    display: grid;
    justify-items: start;
    gap: var(--space-5);
    padding-block: var(--space-6);
    border-top: 0.0625rem solid var(--color-edge);
}

.cart__empty-text {
    max-width: 36ch;
    font-size: var(--font-size-lg);
    line-height: 1.4;
}

.cart__body {
    display: grid;
    gap: var(--space-6);
    align-items: start;
}

.cart__lines {
    border-top: 0.0625rem solid var(--color-edge);
}

.cart__more {
    font-size: var(--font-size-sm);
}

.cart__loading {
    display: grid;
    border-top: 0.0625rem solid var(--color-edge);
}

.cart__placeholder {
    height: 8rem;
    border-bottom: 0.0625rem solid var(--color-edge);
    background: linear-gradient(var(--color-wash-leaf), var(--color-wash-leaf)) 0 50% / 5rem 5rem no-repeat;
    opacity: 0.6;
}

@media (min-width: 56rem) {
    .cart__body {
        grid-template-columns: minmax(0, 8fr) minmax(0, 4fr);
        column-gap: var(--space-8);
    }

    .cart__summary {
        grid-row: span 2;
        position: sticky;
        top: var(--space-6);
    }

    .cart__more {
        grid-column: 1;
    }
}
</style>
