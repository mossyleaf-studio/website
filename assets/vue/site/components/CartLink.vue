<script setup>
import { ShoppingBag } from '@lucide/vue';
import { inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    url: { type: String, required: true },
    current: { type: Boolean, default: false },
});

const { t } = useI18n();
const { units } = inject('cart');
const announcement = ref('');

watch(units, (count) => {
    announcement.value = t('cart.announce', { count }, count);
});
</script>

<template>
    <div class="cart-link">
        <a class="cart-link__link" :href="url" :aria-current="current ? 'page' : undefined" data-test="cart-link">
            <ShoppingBag class="cart-link__icon" aria-hidden="true" />
            <span class="cart-link__word">{{ $t('cart.link') }}</span>
            <span :class="['cart-link__count', { 'cart-link__count--empty': !units }]" aria-hidden="true" data-test="cart-count">{{ units }}</span>
            <span class="visually-hidden">{{ $t('cart.units', { count: units }, units) }}</span>
        </a>
        <span class="visually-hidden" role="status" data-test="cart-announcement">{{ announcement }}</span>
    </div>
</template>

<style scoped>
.cart-link__link {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    min-height: var(--tap);
    padding: 0 var(--space-2) 0 var(--space-3);
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--color-on-forest);
    text-decoration: none;
    background: transparent;
    border: 0.0625rem solid var(--color-on-forest);
    border-radius: var(--radius-pill);
}

.cart-link__link:hover,
.cart-link__link[aria-current='page'] {
    background: color-mix(in oklch, var(--color-on-forest) 14%, transparent);
}

.cart-link__icon {
    width: 1.25rem;
    height: 1.25rem;
    stroke-width: 2;
}

.cart-link__word {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
}

.cart-link__count {
    display: grid;
    place-items: center;
    min-width: 1.75rem;
    height: 1.75rem;
    padding-inline: var(--space-1);
    font-size: var(--font-size-xs);
    line-height: 1;
    font-variant-numeric: tabular-nums;
    color: var(--color-forest);
    background: var(--color-leaf);
    border-radius: var(--radius-pill);
}

.cart-link__count--empty {
    color: var(--color-on-forest);
    background: transparent;
    box-shadow: inset 0 0 0 0.0625rem var(--color-on-forest-muted);
}

@media (min-width: 40rem) {
    .cart-link__word {
        position: static;
        width: auto;
        height: auto;
        overflow: visible;
        clip-path: none;
    }
}
</style>
