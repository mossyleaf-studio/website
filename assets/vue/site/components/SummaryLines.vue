<script setup>
import { useI18n } from 'vue-i18n';
import { formatPrice } from '../money.js';

defineProps({
    lines: { type: Array, required: true },
    label: { type: String, required: true },
});

const { locale } = useI18n();
</script>

<template>
    <ul class="summary-lines" :aria-label="label" data-test="summary-lines">
        <li v-for="line in lines" :key="`${line.title}-${line.variant}-${line.unitPrice}`" class="summary-lines__line" data-test="summary-line">
            <span v-if="'image' in line" class="summary-lines__photo" aria-hidden="true">
                <img v-if="line.image" :src="line.image.src" :srcset="line.image.srcset" sizes="3.5rem" :width="line.image.width" :height="line.image.height" alt="" loading="lazy" />
            </span>
            <span class="summary-lines__product">
                <span class="summary-lines__title">{{ line.title }}</span>
                <span v-if="line.variant" class="summary-lines__variant">{{ line.variant }}</span>
            </span>
            <span class="summary-lines__quantity">× {{ line.quantity }}</span>
            <span class="summary-lines__total">{{ formatPrice(line.lineTotal, locale) }}</span>
        </li>
    </ul>
</template>

<style scoped>
.summary-lines {
    display: grid;
    border-block: 0.0625rem solid var(--color-edge);
}

.summary-lines__line {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto auto;
    gap: var(--space-3);
    align-items: center;
    padding-block: var(--space-3);
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    line-height: 1.3;
}

.summary-lines__line + .summary-lines__line {
    border-top: 0.0625rem dashed var(--color-edge);
}

.summary-lines__photo {
    display: block;
    width: 3.5rem;
    aspect-ratio: 1;
    overflow: hidden;
    background: var(--color-wash-leaf);
    border-radius: var(--radius-photo);
}

.summary-lines__photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.summary-lines__line:not(:has(.summary-lines__photo)) {
    grid-template-columns: minmax(0, 1fr) auto auto;
}

.summary-lines__product {
    display: grid;
}

.summary-lines__title {
    font-weight: 700;
}

.summary-lines__variant {
    font-size: var(--font-size-xs);
    color: var(--color-muted);
}

.summary-lines__quantity {
    color: var(--color-muted);
    font-variant-numeric: tabular-nums;
}

.summary-lines__total {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    text-align: end;
}
</style>
