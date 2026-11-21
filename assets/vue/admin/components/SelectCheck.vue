<script setup>
import BaseCheckbox from '../../components/ui/BaseCheckbox.vue';

defineProps({
    label: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});
const checked = defineModel({ type: [Boolean, String], required: true });
</script>

<template>
    <label :class="['select-check', { 'select-check--off': disabled }]">
        <span class="select-check__box"><BaseCheckbox v-model="checked" :aria-label="label" :disabled="disabled" /></span>
        <span v-if="$slots.default" class="select-check__text"><slot /></span>
    </label>
</template>

<style scoped>
.select-check {
    position: relative;
    z-index: 1;
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: var(--space-2);
    min-height: 2.5rem;
    margin: calc(-1 * var(--space-2)) 0;
    cursor: pointer;
}

.select-check:has(.select-check__text) { padding-right: var(--space-2); }
.select-check__box { display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; }
.select-check--off { cursor: not-allowed; }
.select-check__text { color: var(--color-muted); font-size: var(--font-size-sm); font-weight: 500; }
</style>
