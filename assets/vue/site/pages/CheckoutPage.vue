<script setup>
import { ChevronDown } from '@lucide/vue';
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError } from '../../composables/apiError.js';
import { postJson } from '../api.js';
import { issueNotices, pricedLines } from '../cart.js';
import { orderPayload, serverErrors } from '../checkoutValidation.js';
import { formatPrice } from '../money.js';
import { chosenCountry } from '../shipping.js';
import CheckoutForm from '../components/CheckoutForm.vue';
import Notice from '../components/Notice.vue';
import OrderSummary from '../components/OrderSummary.vue';
import PillButton from '../components/PillButton.vue';
import SummaryLines from '../components/SummaryLines.vue';

const props = defineProps({
    data: { type: Object, required: true },
});

const { t, locale } = useI18n();
const cart = inject('cart');
const quote = ref(null);
const pending = ref(false);
const quoteFailure = ref(null);
const failure = ref(null);
const outdated = ref(null);
const sending = ref(false);
const open = ref(false);
const form = ref(null);
const notice = ref(null);
const placedUrl = ref(null);
const returned = ref(false);

const stored = computed(() => cart.cart.value);
const country = computed({
    get: () => chosenCountry(stored.value.country, props.data.countries, props.data.defaultCountry),
    set: (code) => cart.setCountry(code),
});
const request = computed(() => JSON.stringify({ lines: stored.value.lines.map(({ sku, quantity }) => ({ sku, quantity })), country: country.value }));
const lines = computed(() => (quote.value ? pricedLines(stored.value, quote.value) : []));
const zone = computed(() => quote.value?.shipping?.zone ?? null);
const payable = computed(() => quote.value !== null && quote.value.deliverable && !pending.value && quoteFailure.value === null);
const total = computed(() => (quote.value ? formatPrice(quote.value.total, locale.value) : ''));

const leaveForTheCart = () => window.location.replace(props.data.cartUrl);

async function ask() {
    const sent = request.value;
    quoteFailure.value = null;
    if (stored.value.lines.length === 0) return leaveForTheCart();

    pending.value = true;
    try {
        const answer = await postJson(props.data.quoteUrl, JSON.parse(sent));
        if (sent !== request.value) return;
        if (answer.lines.length === 0) return leaveForTheCart();
        quote.value = answer;
        cart.settle(answer);
        pending.value = false;
        return answer;
    } catch (error) {
        if (sent !== request.value) return;
        quoteFailure.value = error instanceof ApiError && error.status === 429 ? 'tooMany' : 'other';
        pending.value = false;
    }
}

watch(request, (current, previous) => {
    if (JSON.parse(current).country !== JSON.parse(previous).country) {
        outdated.value = null;
        ask();
    }
});

function restored(event) {
    if (!event.persisted) return;
    sending.value = false;
    returned.value = placedUrl.value !== null;
    ask();
}

onBeforeUnmount(() => window.removeEventListener('pageshow', restored));

onMounted(async () => {
    window.addEventListener('pageshow', restored);
    const answer = await ask();
    if (answer?.issues.length) outdated.value = issueNotices(answer.issues, t, locale.value);
});

async function showNotice() {
    await nextTick();
    notice.value?.$el.focus();
}

async function refresh() {
    const answer = await ask();
    if (!answer) return;
    outdated.value = [t('checkout.failure.outdated', { amount: formatPrice(answer.total, locale.value) }), ...issueNotices(answer.issues, t, locale.value)];
    await showNotice();
}

function failureOf(error) {
    if (error instanceof ApiError && error.status === 429) return 'tooMany';
    return 'payment';
}

async function place(delivery) {
    if (!payable.value || sending.value) return;
    failure.value = null;
    outdated.value = null;
    returned.value = false;
    sending.value = true;
    try {
        const placed = await postJson(props.data.ordersUrl, orderPayload(delivery, { lines: stored.value.lines, country: country.value, expectedTotal: quote.value.total, zone: zone.value }));
        cart.placed(placed.reference);
        placedUrl.value = placed.statusUrl;
        window.location.assign(placed.paymentUrl);
        return;
    } catch (error) {
        sending.value = false;
        const fields = error instanceof ApiError && error.status === 422 ? serverErrors(error.violations) : {};
        if (Object.keys(fields).length) return form.value.showErrors(fields);
        if (error instanceof ApiError && error.code === 'sales.empty_cart') return leaveForTheCart();
        if (error instanceof ApiError && (error.status === 409 || error.status === 422)) return refresh();
        failure.value = failureOf(error);
        await showNotice();
    }
}
</script>

<template>
    <article class="checkout" data-test="checkout">
        <header class="checkout__header">
            <h1 class="checkout__title">{{ data.title }}</h1>
            <a class="checkout__back" :href="data.cartUrl" data-cart-back>{{ $t('checkout.backToCart') }}</a>
        </header>

        <Notice v-if="quoteFailure" class="checkout__quote-failure" tone="error" role="alert" data-test="checkout-quote-failure">
            <p>{{ $t(`cart.failure.${quoteFailure}`) }}</p>
            <PillButton class="checkout__retry" tone="paper" @click="ask">{{ $t('cart.failure.retry') }}</PillButton>
        </Notice>

        <div class="checkout__body">
            <aside class="checkout__aside">
                <button class="checkout__toggle" type="button" aria-controls="checkout-summary" :aria-expanded="open ? 'true' : 'false'" data-test="checkout-summary-toggle" @click="open = !open">
                    <span>{{ open ? $t('checkout.hideSummary') : $t('checkout.showSummary') }}</span>
                    <ChevronDown :class="['checkout__chevron', { 'checkout__chevron--open': open }]" aria-hidden="true" />
                    <strong class="checkout__toggle-total">{{ total }}</strong>
                </button>
                <div id="checkout-summary" :class="['checkout__summary', { 'checkout__summary--open': open }]">
                    <OrderSummary v-if="quote" :quote="quote" :countries="data.countries" :choose-country="false" :busy="pending || quoteFailure !== null">
                        <SummaryLines :lines="lines" :label="$t('cart.lines')" />
                    </OrderSummary>
                    <div v-else-if="!quoteFailure" class="checkout__placeholder" aria-busy="true" data-test="checkout-loading">
                        <span class="visually-hidden">{{ $t('checkout.loading') }}</span>
                    </div>
                </div>
            </aside>

            <CheckoutForm ref="form" v-model:country="country" class="checkout__form" :countries="data.countries" :terms-url="data.termsUrl" :zone="zone" :total="quote ? quote.total : null" :busy="!payable" :sending="sending" @submit="place">
                <template #notice>
                    <Notice v-if="returned" tone="success" :title="$t('checkout.placed.title')" data-test="checkout-placed">
                        <p>{{ $t('checkout.placed.text') }}</p>
                        <p><a :href="placedUrl">{{ $t('checkout.placed.link') }}</a></p>
                    </Notice>
                    <Notice v-if="outdated" ref="notice" tone="error" :title="$t('cart.issues.title')" tabindex="-1" role="alert" data-test="checkout-outdated">
                        <p v-for="text in outdated" :key="text">{{ text }}</p>
                    </Notice>
                    <Notice v-else-if="failure" ref="notice" tone="error" tabindex="-1" role="alert" data-test="checkout-failure">
                        <p>{{ $t(`checkout.failure.${failure}`) }}</p>
                    </Notice>
                </template>
            </CheckoutForm>
        </div>
    </article>
</template>

<style scoped>
.checkout {
    display: grid;
    gap: var(--space-6);
}

.checkout__header {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--space-2) var(--space-5);
}

.checkout__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2.125rem, 1.5rem + 2.5vw, var(--font-size-3xl));
    line-height: 1.08;
}

.checkout__back {
    display: inline-flex;
    align-items: center;
    min-height: var(--tap);
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
}

.checkout__quote-failure {
    max-width: 44rem;
}

.checkout__retry {
    justify-self: start;
    margin-top: var(--space-2);
}

.checkout__body {
    display: grid;
    gap: var(--space-6);
    align-items: start;
}

.checkout__aside {
    display: grid;
    gap: var(--space-3);
}

.checkout__toggle {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    width: 100%;
    min-height: var(--tap);
    padding: var(--space-2) var(--space-4);
    font: inherit;
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    color: var(--color-ink);
    text-align: start;
    background: var(--color-card);
    border: var(--border-strong);
    border-radius: var(--radius-photo);
    cursor: pointer;
}

.checkout__toggle:focus-visible {
    outline: 0.1875rem solid var(--color-ink);
    outline-offset: 0.125rem;
}

.checkout__chevron {
    width: 1.25rem;
    height: 1.25rem;
    transition: rotate var(--transition);
}

.checkout__chevron--open {
    rotate: 180deg;
}

.checkout__toggle-total {
    margin-inline-start: auto;
    font-size: var(--font-size-lg);
    font-variant-numeric: tabular-nums;
}

.checkout__summary:not(.checkout__summary--open) {
    display: none;
}

.checkout__summary {
    padding-top: var(--space-4);
}

.checkout__placeholder {
    min-height: 16rem;
    background: var(--color-card);
    border: 0.0625rem solid var(--color-edge);
    border-radius: var(--radius-sm);
    opacity: 0.6;
}

.checkout__form {
    max-width: 36rem;
}

@media (min-width: 56rem) {
    .checkout__body {
        grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
        column-gap: var(--space-8);
    }

    .checkout__aside {
        grid-column: 2;
        grid-row: 1;
        position: sticky;
        top: var(--space-6);
    }

    .checkout__form {
        grid-column: 1;
        grid-row: 1;
    }

    .checkout__toggle {
        display: none;
    }

    .checkout__summary:not(.checkout__summary--open) {
        display: block;
    }

    .checkout__summary {
        padding-top: var(--space-3);
    }
}

@media (prefers-reduced-motion: reduce) {
    .checkout__chevron {
        transition: none;
    }
}
</style>
