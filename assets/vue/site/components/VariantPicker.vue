<script setup>
defineProps({
    variants: { type: Array, required: true },
    name: { type: String, required: true },
});

const sku = defineModel({ type: String, required: true });
</script>

<template>
    <fieldset class="variant-picker" data-test="variant-picker">
        <legend class="variant-picker__legend">{{ $t('product.variants') }}</legend>
        <div class="variant-picker__options">
            <label v-for="variant in variants" :key="variant.sku" :class="['variant-picker__option', { 'variant-picker__option--sold-out': variant.soldOut, 'variant-picker__option--chosen': sku === variant.sku && !variant.soldOut }]">
                <input v-model="sku" class="variant-picker__input" type="radio" :name="name" :value="variant.sku" :disabled="variant.soldOut" />
                <span>{{ variant.name }}</span>
                <span v-if="variant.soldOut" class="variant-picker__note">{{ $t('product.soldOut') }}</span>
            </label>
        </div>
    </fieldset>
</template>

<style scoped>
.variant-picker {
    display: grid;
    gap: var(--space-2);
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.variant-picker__legend {
    padding: 0;
    margin-bottom: var(--space-2);
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    font-weight: 600;
}

.variant-picker__options {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
}

.variant-picker__option {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    min-height: var(--control-height);
    min-width: var(--tap);
    padding: 0 var(--space-4);
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    color: var(--color-ink);
    background: var(--color-card);
    border: 0.0625rem solid var(--color-ink);
    border-radius: var(--radius-pill);
    cursor: pointer;
}

.variant-picker__option:hover {
    background: var(--color-wash-leaf);
}

.variant-picker__option--chosen {
    font-weight: 600;
    background: var(--color-meadow);
    border: var(--border-strong);
}

.variant-picker__input:focus-visible + span::after {
    content: '';
    position: absolute;
    inset: -0.125rem;
    border-radius: var(--radius-pill);
    outline: 0.1875rem solid var(--color-ink);
    outline-offset: 0.1875rem;
    pointer-events: none;
}

.variant-picker__option--sold-out {
    color: var(--color-muted);
    background: var(--color-paper);
    border-style: dashed;
    cursor: not-allowed;
}

.variant-picker__option--sold-out:hover {
    background: var(--color-paper);
}

.variant-picker__input {
    position: absolute;
    inset: 0;
    margin: 0;
    opacity: 0;
    cursor: inherit;
}

.variant-picker__note {
    font-size: var(--font-size-xs);
}
</style>
