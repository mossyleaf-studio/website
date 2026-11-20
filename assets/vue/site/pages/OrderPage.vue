<script setup>
import { inject, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { getJson } from '../api.js';
import { POLL_INTERVAL, keepAsking, statusView } from '../orderStatus.js';
import OrderStatus from '../components/OrderStatus.vue';

const props = defineProps({
    data: { type: Object, required: true },
    cartUrl: { type: String, required: true },
});

const cart = inject('cart');
const order = ref(props.data.order);
const stalled = ref(false);
const startedAt = Date.now();
const pageUrl = window.location.pathname;
let timer = null;

async function ask() {
    order.value = await getJson(props.data.statusUrl).catch(() => order.value);
    schedule();
}

function schedule() {
    if (keepAsking(order.value.status, startedAt, Date.now())) timer = setTimeout(ask, POLL_INTERVAL);
    else stalled.value = order.value.status === 'pending';
}

watch(
    () => statusView(order.value).settled,
    (settled) => settled && cart.paid(order.value.reference),
    { immediate: true },
);

onMounted(schedule);
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <article class="order" data-test="order">
        <h1 class="order__title">{{ data.title }}</h1>
        <OrderStatus class="order__sheet" :order="order" :stalled="stalled" :cart-url="cartUrl" :page-url="pageUrl" />
    </article>
</template>

<style scoped>
.order {
    display: grid;
    gap: var(--space-7);
    width: 100%;
    max-width: 44rem;
    margin-inline: auto;
}

.order__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2.125rem, 1.5rem + 2.5vw, var(--font-size-3xl));
    line-height: 1.08;
}
</style>
