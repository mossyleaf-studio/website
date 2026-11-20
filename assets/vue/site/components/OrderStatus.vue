<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatPrice } from '../money.js';
import { statusView, trackingOf } from '../orderStatus.js';
import PaperSheet from './PaperSheet.vue';
import PillButton from './PillButton.vue';
import SummaryLines from './SummaryLines.vue';
import TapeLabel from './TapeLabel.vue';

const props = defineProps({
    order: { type: Object, required: true },
    stalled: { type: Boolean, default: false },
    cartUrl: { type: String, required: true },
    pageUrl: { type: String, required: true },
});

const { locale } = useI18n();
const price = (cents) => formatPrice(cents, locale.value);
const view = computed(() => statusView(props.order, { stalled: props.stalled }));
const tracking = computed(() => trackingOf(props.order));
</script>

<template>
    <PaperSheet tag="section" tape="leaf" :class="['order-status', `order-status--${view.state}`]" aria-labelledby="order-status-title" data-test="order-status" :data-state="view.state">
        <div class="order-status__top">
            <p class="order-status__reference" data-test="order-reference">{{ $t('order.reference', { reference: order.reference }) }}</p>
            <TapeLabel v-if="order.test" class="order-status__test" tone="blossom" data-test="order-test">{{ $t('order.test') }}</TapeLabel>
        </div>

        <div class="order-status__state" aria-live="polite">
            <h2 id="order-status-title" class="order-status__title" data-test="order-state">
                <span v-if="view.progress" class="order-status__progress" aria-hidden="true"><span /><span /><span /></span>
                {{ $t(`order.state.${view.state}.title`) }}
            </h2>
            <p class="order-status__text">{{ $t(`order.state.${view.state}.text`) }}</p>
            <p v-if="tracking" class="order-status__tracking" data-test="order-tracking">
                {{ $t('order.tracking') }} <strong class="order-status__tracking-number" data-test="order-tracking-number">{{ tracking }}</strong>
            </p>
        </div>

        <div v-if="view.payUrl || view.backToCart || view.reload" class="order-status__actions">
            <PillButton v-if="view.payUrl" :href="view.payUrl" tone="paper" data-test="order-pay">{{ $t('order.pay') }}</PillButton>
            <PillButton v-if="view.backToCart" :href="cartUrl" data-test="order-cart">{{ $t('order.backToCart') }}</PillButton>
            <a v-if="view.reload" class="order-status__reload" :href="pageUrl" data-test="order-reload">{{ $t('order.reload') }}</a>
        </div>

        <div class="order-status__summary">
            <h3 class="order-status__subtitle">{{ $t('order.lines') }}</h3>
            <SummaryLines :lines="order.lines" :label="$t('order.lines')" />
            <dl class="order-status__amounts">
                <div class="order-status__row">
                    <dt>{{ $t('cart.summary.goods') }}</dt>
                    <dd>{{ price(order.goods) }}</dd>
                </div>
                <div class="order-status__row">
                    <dt>{{ $t('cart.summary.shipping') }}</dt>
                    <dd>{{ order.shipping > 0 ? price(order.shipping) : $t('cart.summary.free') }}</dd>
                </div>
                <div class="order-status__row order-status__row--total">
                    <dt>{{ $t('cart.summary.total') }}</dt>
                    <dd data-test="order-total">{{ price(order.total) }}</dd>
                </div>
            </dl>
        </div>
    </PaperSheet>
</template>

<style scoped>
.order-status {
    gap: var(--space-5);
}

.order-status__top {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-2) var(--space-4);
}

.order-status__reference {
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
}

.order-status__test {
    rotate: 3deg;
}

.order-status__state {
    display: grid;
    gap: var(--space-3);
}

.order-status__title {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-3);
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(1.9rem, 1.4rem + 2vw, var(--font-size-3xl));
    line-height: 1.05;
    text-wrap: balance;
}

.order-status__text {
    max-width: 52ch;
    font-size: var(--font-size);
    line-height: 1.5;
}

.order-status__tracking {
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
}

.order-status__tracking-number {
    font-variant-numeric: tabular-nums;
    user-select: all;
}

.order-status__progress {
    display: inline-flex;
    gap: 0.3rem;
}

.order-status__progress span {
    width: 0.625rem;
    height: 0.625rem;
    background: var(--color-leaf);
    border: 0.125rem solid var(--color-ink);
    border-radius: 50%;
    animation: order-status-breathe 1.8s ease-in-out infinite;
}

.order-status__progress span:nth-child(2) {
    animation-delay: 0.3s;
}

.order-status__progress span:nth-child(3) {
    animation-delay: 0.6s;
}

@keyframes order-status-breathe {
    0%,
    100% {
        opacity: 0.35;
        translate: 0 0;
    }

    40% {
        opacity: 1;
        translate: 0 -0.1875rem;
    }
}

.order-status__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-3) var(--space-5);
}

.order-status__reload {
    display: inline-flex;
    align-items: center;
    min-height: var(--tap);
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
}

.order-status__summary {
    display: grid;
    gap: var(--space-3);
    margin-top: var(--space-3);
}

.order-status__subtitle {
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    font-weight: 700;
}

.order-status__amounts {
    display: grid;
    gap: var(--space-2);
}

.order-status__row {
    display: flex;
    justify-content: space-between;
    gap: var(--space-4);
    font-family: var(--font-ui);
}

.order-status__row dd {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.order-status__row--total {
    margin-top: var(--space-1);
    padding-top: var(--space-3);
    font-size: var(--font-size-lg);
    border-top: var(--border-strong);
}

@media (prefers-reduced-motion: reduce) {
    .order-status__progress span {
        animation: none;
        opacity: 1;
    }
}
</style>
