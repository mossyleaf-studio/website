<script setup>
defineProps({
    tone: { type: String, default: 'leaf' },
});
</script>

<template>
    <span :class="['washi', `washi--${tone}`]" aria-hidden="true" />
</template>

<style scoped>
.washi {
    display: block;
    width: 5.5rem;
    height: 1.5rem;
    opacity: 0.9;
    clip-path: polygon(0 0, 100% 0, 97% 25%, 100% 50%, 97% 75%, 100% 100%, 0 100%, 3% 75%, 0 50%, 3% 25%);
}

.washi--leaf {
    background: repeating-linear-gradient(-45deg, var(--color-tape-leaf) 0 0.4rem, color-mix(in oklch, var(--color-tape-leaf) 55%, white) 0.4rem 0.8rem);
}

.washi--blossom {
    background:
        radial-gradient(circle, color-mix(in oklch, var(--color-tape-blossom) 40%, white) 0 0.14rem, transparent 0.16rem) 0 0 / 0.7rem 0.7rem,
        var(--color-tape-blossom);
}
</style>
