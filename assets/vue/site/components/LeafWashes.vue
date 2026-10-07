<script setup>
const CLUSTERS = {
    canopy: [
        [9, 1, 11, 20, 'leaf'],
        [2, 7, 9, -35, 'sprout'],
        [14, 8, 12, 65, 'moss'],
        [7, 14, 8, 5, 'star'],
        [18, 0, 7, -10, 'sprout'],
        [0, 0, 6, 40, 'leaf'],
    ],
    undergrowth: [
        [4, 10, 12, -60, 'moss'],
        [12, 6, 9, 15, 'sprout'],
        [0, 4, 8, 75, 'leaf'],
        [16, 12, 7, -20, 'star'],
        [8, 1, 6, 50, 'leaf'],
    ],
    fernery: [
        [6, 0, 9, 110, 'moss'],
        [0, 6, 7, 160, 'sprout'],
        [8, 8, 10, 135, 'moss'],
        [3, 13, 6, 95, 'leaf'],
    ],
};
</script>

<template>
    <div class="leaves" aria-hidden="true">
        <svg class="leaves__defs" width="0" height="0">
            <filter id="watercolor" x="-25%" y="-25%" width="150%" height="150%">
                <feTurbulence type="fractalNoise" baseFrequency="0.045" numOctaves="4" seed="11" result="noise" />
                <feDisplacementMap in="SourceGraphic" in2="noise" scale="9" xChannelSelector="R" yChannelSelector="G" result="warp" />
                <feGaussianBlur in="warp" stdDeviation="0.8" />
            </filter>
            <filter id="ink-stamp" x="-10%" y="-10%" width="120%" height="120%">
                <feTurbulence type="fractalNoise" baseFrequency="0.6" numOctaves="2" seed="3" result="noise" />
                <feDisplacementMap in="SourceGraphic" in2="noise" scale="2.5" xChannelSelector="R" yChannelSelector="G" />
            </filter>
            <filter id="graphite" x="-5%" y="-5%" width="110%" height="110%">
                <feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" seed="5" result="grain" />
                <feDisplacementMap in="SourceGraphic" in2="grain" scale="1.2" xChannelSelector="R" yChannelSelector="G" result="rough" />
                <feColorMatrix in="grain" type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 -0.9 1.45" result="speckles" />
                <feComposite in="rough" in2="speckles" operator="in" />
            </filter>
        </svg>

        <div v-for="(leaves, name) in CLUSTERS" :key="name" :class="['leaves__cluster', `leaves__cluster--${name}`]">
            <span
                v-for="([x, y, size, rotate, tone], index) in leaves"
                :key="index"
                class="leaves__leaf"
                :style="{
                    left: `${x}rem`,
                    top: `${y}rem`,
                    width: `${size}rem`,
                    height: `${size}rem`,
                    rotate: `${rotate}deg`,
                    '--tone': `var(--color-${tone})`,
                }"
            />
        </div>
    </div>
</template>

<style scoped>
.leaves {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
}

.leaves__defs {
    position: absolute;
}

.leaves__cluster {
    position: absolute;
    width: 28rem;
    height: 24rem;
    scale: 0.55;
}

.leaves__cluster--canopy {
    top: -3rem;
    right: -5rem;
    transform-origin: top right;
}

.leaves__cluster--undergrowth {
    bottom: -4rem;
    left: -6rem;
    transform-origin: bottom left;
}

.leaves__cluster--fernery {
    display: none;
    top: 34rem;
    right: -7rem;
    width: 18rem;
    transform-origin: top right;
}

.leaves__leaf {
    position: absolute;
    border-radius: 0 100% 0 100%;
    background:
        linear-gradient(45deg, transparent 49.4%, color-mix(in oklch, var(--color-card) 55%, transparent) 49.6% 50.4%, transparent 50.6%),
        radial-gradient(closest-side, color-mix(in oklch, var(--tone) 30%, transparent) 30%, color-mix(in oklch, var(--tone) 70%, transparent) 100%);
    mix-blend-mode: multiply;
    filter: url(#watercolor);
}

@media (min-width: 40rem) {
    .leaves__cluster {
        scale: 0.8;
    }
}

@media (min-width: 56rem) {
    .leaves__cluster {
        scale: 1;
    }

    .leaves__cluster--fernery {
        display: block;
    }
}
</style>
