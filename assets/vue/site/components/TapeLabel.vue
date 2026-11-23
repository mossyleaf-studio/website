<script setup>
defineProps({
    tone: { type: String, default: 'leaf' },
});
</script>

<template>
    <span :class="['tape-label', `tape-label--${tone}`]" data-label><slot /></span>
</template>

<style scoped>
.tape-label {
    display: inline-block;
    padding: var(--space-1) var(--space-4);
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    font-weight: 600;
    line-height: 1.5;
    color: var(--color-ink);
    white-space: nowrap;
    rotate: -2deg;
    clip-path: polygon(0 0, 100% 0, 97% 25%, 100% 50%, 97% 75%, 100% 100%, 0 100%, 3% 75%, 0 50%, 3% 25%);
    box-shadow: inset 0 -0.0625rem 0 color-mix(in oklch, var(--color-ink) 15%, transparent);
}

.tape-label--leaf {
    background: color-mix(in oklch, var(--color-tape-leaf) 85%, white);
}

.tape-label--blossom {
    color: var(--color-paper);
    background: color-mix(in oklch, var(--color-tape-blossom) 85%, white);
}

.tape-label--star {
    background: var(--color-wash-star);
}
</style>
