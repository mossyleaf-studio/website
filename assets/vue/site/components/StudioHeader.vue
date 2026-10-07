<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    intro: { type: String, required: true },
    logo: { type: Object, default: null },
});

const parts = computed(() => props.name.split(/(?=\.)/));
</script>

<template>
    <header :class="['studio-header', { 'studio-header--with-logo': logo }]">
        <img v-if="logo" class="studio-header__logo" :src="logo.url" :width="logo.width" :height="logo.height" alt="" />
        <div class="studio-header__text">
            <h1 class="studio-header__wordmark">
                <template v-for="(part, index) in parts" :key="index"><wbr v-if="index" />{{ part }}</template>
            </h1>
            <p class="studio-header__intro">{{ intro }}</p>
        </div>
    </header>
</template>

<style scoped>
.studio-header {
    display: grid;
    gap: var(--space-5);
}

.studio-header__text {
    display: grid;
    gap: var(--space-5);
    min-width: 0;
}

.studio-header__logo {
    width: auto;
    height: auto;
    max-width: min(60%, 12rem);
    max-height: 7rem;
    object-fit: contain;
    object-position: left center;
}

.studio-header__wordmark {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: clamp(3rem, 15vw, 6rem);
    line-height: 0.92;
    letter-spacing: -0.015em;
    color: var(--color-ink);
    overflow-wrap: break-word;
}

.studio-header__intro {
    max-width: 30ch;
    font-size: var(--font-size-lg);
    line-height: 1.4;
    color: var(--color-muted);
}

@media (min-width: 40rem) {
    .studio-header--with-logo {
        grid-template-columns: auto minmax(0, 1fr);
        align-items: center;
        column-gap: var(--space-6);
    }

    .studio-header__logo {
        max-width: 11rem;
        max-height: 11rem;
    }
}
</style>
