<script setup>
import { SwitchRoot, SwitchThumb } from 'reka-ui';

defineProps({
    label: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});

const model = defineModel({ type: Boolean, required: true });
</script>

<template>
    <SwitchRoot v-model="model" class="base-switch" :aria-label="label" :disabled="disabled">
        <SwitchThumb class="base-switch__thumb" />
    </SwitchRoot>
</template>

<style scoped>
.base-switch {
    position: relative;
    flex-shrink: 0;
    width: 2.5rem;
    height: 1.5rem;
    padding: 0;
    border: 0.0625rem solid var(--color-border-strong);
    border-radius: var(--radius-pill);
    background: var(--color-bg);
    cursor: pointer;
    transition: background var(--transition), border-color var(--transition);
}

.base-switch[data-state="checked"] { border-color: var(--color-accent); background: var(--color-accent); }
.base-switch:disabled { opacity: 0.4; cursor: not-allowed; }

.base-switch__thumb {
    display: block;
    width: 1.125rem;
    height: 1.125rem;
    border-radius: 50%;
    background: var(--color-muted);
    transform: translateX(0.1875rem);
    transition: transform var(--transition), background var(--transition);
}

.base-switch__thumb[data-state="checked"] { background: var(--color-on-accent); transform: translateX(1.1875rem); }
</style>
