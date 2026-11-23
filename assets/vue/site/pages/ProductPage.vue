<script setup>
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumb from '../components/Breadcrumb.vue';
import BuyBox from '../components/BuyBox.vue';
import Notice from '../components/Notice.vue';
import ProductGallery from '../components/ProductGallery.vue';
import ProductGrid from '../components/ProductGrid.vue';
import RichText from '../components/RichText.vue';
import TapeLabel from '../components/TapeLabel.vue';
import { formatPrice } from '../money.js';

const props = defineProps({
    data: { type: Object, required: true },
    shop: { type: String, required: true },
    cartUrl: { type: String, required: true },
});

const cart = inject('cart');
const added = ref(null);
const add = (line) => {
    const available = props.data.variants.find((variant) => variant.sku === line.sku)?.available ?? 0;
    added.value = cart.add(line, available) ? 'added' : 'full';
};

const { t, locale } = useI18n();
const trail = computed(() => [{ label: t('nav.shop'), url: props.shop }, { label: props.data.category.label, url: props.data.category.url }, { label: props.data.title }]);
const price = computed(() => formatPrice(props.data.price, locale.value));
const paragraphs = computed(() =>
    props.data.description
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean),
);
</script>

<template>
    <article class="product">
        <Breadcrumb :items="trail" />
        <div class="product__body">
            <ProductGallery class="product__gallery" :photos="data.photos" />
            <div class="product__details">
                <p v-if="data.hidden" class="product__hidden" data-hidden>
                    <TapeLabel tone="leaf">{{ $t('product.hidden') }}</TapeLabel>
                    <span>{{ $t('product.hiddenNote') }}</span>
                </p>
                <h1 class="product__title">{{ data.title }}</h1>
                <div class="product__price">
                    <p class="product__amount" data-price>{{ price }}</p>
                    <p v-if="data.priceNote" class="product__note" data-price-note>{{ data.priceNote }}</p>
                </div>
                <BuyBox :variants="data.variants" @add="add" />
                <div class="product__added" role="status">
                    <Notice v-if="added" :tone="added === 'added' ? 'success' : 'error'" data-test="cart-added">
                        <p>
                            {{ $t(`product.${added}`) }}
                            <a :href="cartUrl" data-test="see-cart">{{ $t('product.seeCart') }}</a>
                        </p>
                    </Notice>
                </div>
                <div v-if="paragraphs.length" class="product__description" data-description>
                    <p v-for="(paragraph, index) in paragraphs" :key="index"><RichText :text="paragraph" /></p>
                </div>
            </div>
        </div>
        <section v-if="data.related.length" class="product__related" aria-labelledby="related-title" data-related>
            <h2 id="related-title" class="product__related-title">{{ $t('product.related') }}</h2>
            <ProductGrid :products="data.related" :columns="4" />
        </section>
    </article>
</template>

<style scoped>
.product {
    display: grid;
    gap: var(--space-5);
}

.product__body {
    display: grid;
    gap: var(--space-6);
    align-items: start;
}

.product__details {
    display: grid;
    gap: var(--space-5);
    justify-items: start;
}

.product__hidden {
    display: grid;
    justify-items: start;
    gap: var(--space-2);
    max-width: 32ch;
    font-size: var(--font-size-sm);
}

.product__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2rem, 1.5rem + 2vw, var(--font-size-3xl));
    line-height: 1.08;
    text-wrap: balance;
}

.product__price {
    display: grid;
    gap: var(--space-1);
}

.product__amount {
    font-family: var(--font-ui);
    font-size: var(--font-size-xl);
    font-weight: 600;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.product__note {
    font-size: var(--font-size-xs);
    color: var(--color-muted);
}

.product__added {
    justify-self: stretch;
    max-width: 30rem;
}

.product__added:empty {
    margin-top: calc(-1 * var(--space-5));
}

.product__added a {
    font-weight: 600;
    white-space: nowrap;
}

.product__description {
    justify-self: stretch;
    display: grid;
    gap: var(--space-4);
    max-width: var(--measure);
    padding-top: var(--space-5);
    border-top: 0.0625rem solid var(--color-edge);
}

.product__related {
    display: grid;
    gap: var(--space-5);
    margin-top: var(--space-8);
}

.product__related-title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-xl);
    line-height: 1.15;
}

@media (min-width: 56rem) {
    .product__body {
        grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
        gap: var(--space-8);
    }

    .product__details {
        padding-top: var(--space-4);
    }
}
</style>
