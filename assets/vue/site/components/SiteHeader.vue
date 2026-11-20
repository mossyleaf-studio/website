<script setup>
import { computed, ref } from 'vue';
import LanguageSwitch from './LanguageSwitch.vue';

const props = defineProps({
    home: { type: String, required: true },
    studioName: { type: String, required: true },
    logo: { type: Object, default: null },
    navigation: { type: Array, required: true },
    languages: { type: Array, required: true },
});

const open = ref(false);
const entries = computed(() => props.navigation.filter((entry) => entry.key !== 'home'));
</script>

<template>
    <header class="site-header">
        <div class="site-header__bar">
            <LanguageSwitch class="site-header__languages" :languages="languages" />
            <a class="site-header__home" :href="home" data-home>
                <template v-if="logo">
                    <img class="site-header__logo" :src="logo.url" :width="logo.width" :height="logo.height" alt="" />
                    <span class="visually-hidden">{{ studioName }}</span>
                </template>
                <span v-else class="site-header__wordmark">{{ studioName }}</span>
            </a>
            <div class="site-header__end">
                <slot name="end" />
            </div>
        </div>

        <nav class="site-header__navigation" :aria-label="$t('layout.navigation')" data-navigation>
            <button class="site-header__menu" type="button" :aria-expanded="open" aria-controls="site-navigation" @click="open = !open">
                {{ $t('layout.menu') }}
            </button>
            <ul id="site-navigation" :class="['site-header__links', { 'site-header__links--open': open }]">
                <li v-for="entry in entries" :key="entry.key">
                    <a :class="['site-header__link', { 'site-header__link--current': entry.current }]" :href="entry.url" :aria-current="entry.current ? 'page' : undefined">{{ $t(`nav.${entry.key}`) }}</a>
                </li>
            </ul>
        </nav>
    </header>
</template>

<style scoped>
.site-header {
    border-bottom: 0.0625rem solid var(--color-edge);
}

.site-header__bar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
        'languages end'
        'home home';
    align-items: center;
    gap: var(--space-4);
    max-width: calc(var(--page) + 2 * var(--page-gutter));
    margin-inline: auto;
    padding: var(--space-5) var(--page-gutter) var(--space-4);
}

.site-header__languages {
    grid-area: languages;
    justify-self: start;
}

.site-header__home {
    grid-area: home;
    justify-self: center;
    max-width: 100%;
    display: block;
    color: var(--color-ink);
    text-decoration: none;
    border-radius: var(--radius-sm);
}

.site-header__logo {
    width: auto;
    height: auto;
    max-height: 4rem;
    max-width: min(11rem, 40vw);
    object-fit: contain;
}

.site-header__wordmark {
    display: block;
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(1.5rem, 1rem + 2.5vw, var(--font-size-3xl));
    line-height: 1;
    letter-spacing: -0.01em;
    text-align: center;
}

.site-header__end {
    grid-area: end;
    justify-self: end;
}

.site-header__navigation {
    display: grid;
    justify-items: center;
    gap: var(--space-3);
    padding: 0 var(--page-gutter) var(--space-4);
}

.site-header__menu {
    min-height: var(--control-height);
    padding: 0 var(--space-5);
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    font-weight: 700;
    color: var(--color-ink);
    background: var(--color-paper);
    border: var(--border-strong);
    border-radius: var(--radius-pill);
    cursor: pointer;
}

.site-header__menu[aria-expanded='true'] {
    background: var(--color-wash-leaf);
}

.site-header__links {
    display: none;
    justify-items: center;
    width: 100%;
}

.site-header__links--open {
    display: grid;
}

.site-header__link {
    display: flex;
    align-items: center;
    min-height: var(--tap);
    padding-inline: var(--space-4);
    font-size: var(--font-size);
    color: var(--color-ink);
    text-decoration: none;
    text-underline-offset: 0.3em;
}

.site-header__link:hover,
.site-header__link--current {
    text-decoration: underline;
    text-decoration-thickness: 0.125rem;
}

@media (min-width: 40rem) {
    .site-header__bar {
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        grid-template-areas: 'languages home end';
    }
}

@media (min-width: 56rem) {
    .site-header__bar {
        padding-block: var(--space-6) var(--space-4);
    }

    .site-header__logo {
        max-height: 5.5rem;
    }

    .site-header__menu {
        display: none;
    }

    .site-header__links {
        display: flex;
        justify-content: center;
        gap: var(--space-5);
    }
}
</style>
