<script setup>
import { Check, Minus } from '@lucide/vue';
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui';
</script>

<template>
    <CheckboxRoot v-slot="{ state }" class="checkbox">
        <CheckboxIndicator class="checkbox__indicator">
            <Minus v-if="state === 'indeterminate'" size="0.75rem" :stroke-width="3" aria-hidden="true" />
            <Check v-else size="0.75rem" :stroke-width="3" aria-hidden="true" />
        </CheckboxIndicator>
    </CheckboxRoot>
</template>

<style scoped>
.checkbox {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 1.125rem;
    height: 1.125rem;
    padding: 0;
    border: 0.0625rem solid var(--color-border-strong);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    color: var(--color-on-accent);
    cursor: pointer;
    transition: background var(--transition), border-color var(--transition);
}

.checkbox:hover:not([data-disabled]) { border-color: var(--color-ink); }
.checkbox[data-state="checked"],
.checkbox[data-state="indeterminate"] { background: var(--color-accent); border-color: var(--color-accent); }
.checkbox[data-disabled] { opacity: 0.6; cursor: not-allowed; }
.checkbox:focus-visible { outline: 0.125rem solid var(--color-accent); outline-offset: 0.125rem; }
.checkbox__indicator { display: inline-flex; }
</style>
