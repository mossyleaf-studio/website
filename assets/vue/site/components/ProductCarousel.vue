<script setup>
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import { carouselState } from '../carousel.js';
import ProductCard from './ProductCard.vue';

const props = defineProps({
    products: { type: Array, required: true },
    labelledby: { type: String, required: true },
    eager: { type: Boolean, default: false },
});

const listId = `${props.labelledby}-products`;
const list = useTemplateRef('list');
const state = ref(carouselState({ scrollLeft: 0, clientWidth: 0, scrollWidth: 0 }));
let observer = null;

const follow = () => {
    state.value = carouselState(list.value);
};

const scroll = (direction) => {
    const style = getComputedStyle(list.value);
    const page = list.value.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
    list.value.scrollBy({ left: direction * page });
};

onMounted(() => {
    follow();
    list.value.addEventListener('scroll', follow, { passive: true });
    observer = new ResizeObserver(follow);
    observer.observe(list.value);
});

onBeforeUnmount(() => {
    list.value?.removeEventListener('scroll', follow);
    observer?.disconnect();
});
</script>

<template>
    <div class="product-carousel" data-carousel>
        <div class="product-carousel__head">
            <slot />
            <div v-if="state.needed" class="product-carousel__buttons">
                <button class="product-carousel__button" type="button" :aria-label="$t('carousel.previous')" :aria-controls="listId" :aria-disabled="!state.previous" data-carousel-previous @click="state.previous && scroll(-1)">
                    <ChevronLeft :size="20" aria-hidden="true" />
                </button>
                <button class="product-carousel__button" type="button" :aria-label="$t('carousel.next')" :aria-controls="listId" :aria-disabled="!state.next" data-carousel-next @click="state.next && scroll(1)">
                    <ChevronRight :size="20" aria-hidden="true" />
                </button>
            </div>
        </div>
        <ul :id="listId" ref="list" class="product-carousel__list" :aria-labelledby="labelledby" data-products>
            <li v-for="(product, index) in products" :key="product.url" class="product-carousel__item">
                <ProductCard :product="product" :eager="eager && index === 0" />
            </li>
        </ul>
    </div>
</template>

<style scoped>
.product-carousel {
    --bleed: 0.75rem;
    --bleed-inline: var(--page-gutter);
    --gap: 0.9375rem;
    --in-view: 2.4;

    display: grid;
    gap: calc(var(--space-5) - var(--bleed));
}

.product-carousel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    min-height: var(--tap);
}

.product-carousel__buttons {
    display: flex;
    flex: none;
    gap: var(--space-2);
}

.product-carousel__button {
    display: grid;
    place-items: center;
    width: var(--tap);
    height: var(--tap);
    padding: 0;
    color: var(--color-ink);
    background: transparent;
    border: 0.0625rem solid var(--color-ink);
    border-radius: var(--radius-pill);
    cursor: pointer;
}

.product-carousel__button:hover:not([aria-disabled='true']) {
    background: var(--color-wash-leaf);
}

.product-carousel__button[aria-disabled='true'] {
    opacity: 0.35;
    cursor: default;
}

.product-carousel__list {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: calc((100% - (var(--in-view) - 1) * var(--gap)) / var(--in-view));
    column-gap: var(--gap);
    margin: 0 calc(-1 * var(--bleed-inline));
    padding: var(--bleed) var(--bleed-inline);
    overflow-x: auto;
    overscroll-behavior-x: contain;
    scroll-snap-type: x mandatory;
    scroll-padding-inline: var(--bleed-inline);
    scrollbar-width: thin;
    scrollbar-color: var(--color-bark) transparent;
    list-style: none;
}

.product-carousel__item {
    display: grid;
    min-width: 0;
    scroll-snap-align: start;
}

@media (prefers-reduced-motion: no-preference) {
    .product-carousel__list {
        scroll-behavior: smooth;
    }
}

@media (min-width: 40rem) {
    .product-carousel {
        --in-view: 3;
    }
}

@media (min-width: 56rem) {
    .product-carousel {
        --in-view: 5;
    }
}
</style>
