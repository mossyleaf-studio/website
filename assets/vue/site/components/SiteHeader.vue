<script setup>
import { Menu } from '@lucide/vue';
import { ref } from 'vue';
import LanguageSwitch from './LanguageSwitch.vue';

defineProps({
    home: { type: String, required: true },
    studioName: { type: String, required: true },
    logo: { type: Object, default: null },
    navigation: { type: Array, required: true },
    languages: { type: Array, required: true },
});

const open = ref(false);
</script>

<template>
    <header class="site-header">
        <div class="site-header__bar">
            <LanguageSwitch class="site-header__languages" :languages="languages" tone="forest" />
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
                <Menu class="site-header__menu-icon" aria-hidden="true" />
                {{ $t('layout.menu') }}
            </button>
            <ul id="site-navigation" :class="['site-header__links', { 'site-header__links--open': open }]">
                <li v-for="entry in navigation" :key="entry.key">
                    <a :class="['site-header__link', { 'site-header__link--current': entry.current }]" :href="entry.url" :aria-current="entry.current ? 'page' : undefined">{{ $t(`nav.${entry.key}`) }}</a>
                </li>
            </ul>
        </nav>
    </header>
</template>

<style scoped>
.site-header {
    --deckle: 1.5rem;
    color: var(--color-on-forest);
    background:
        url('../../../styles/site/paper/tooth.svg') 0 0 / 20rem 20rem,
        var(--color-forest);
    background-blend-mode: multiply, normal;
    padding-bottom: var(--deckle);
    mask:
        linear-gradient(#000 0 0) top / 100% calc(100% - var(--deckle) + 0.25rem) no-repeat,
        url('../../../styles/site/paper/deckle.svg') bottom / 37.5rem var(--deckle) repeat-x;
}

.site-header :deep(:focus-visible) {
    outline-color: var(--color-on-forest);
}

.site-header__bar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
        'languages end'
        'home home';
    align-items: center;
    gap: var(--space-3) var(--space-4);
    max-width: calc(var(--page) + 2 * var(--page-gutter));
    margin-inline: auto;
    padding: var(--space-4) var(--page-gutter) var(--space-2);
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
    color: var(--color-on-forest);
    text-decoration: none;
    border-radius: var(--radius-sm);
}

.site-header__logo {
    width: auto;
    height: auto;
    max-height: 4rem;
    max-width: min(11rem, 50vw);
    object-fit: contain;
}

.site-header__wordmark {
    display: block;
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(1.75rem, 1.25rem + 2.5vw, var(--font-size-3xl));
    line-height: 1;
    text-align: center;
}

.site-header__end {
    grid-area: end;
    justify-self: end;
}

.site-header__navigation {
    display: grid;
    justify-items: center;
    gap: var(--space-2);
    max-width: calc(var(--page) + 2 * var(--page-gutter));
    margin-inline: auto;
    padding: var(--space-2) var(--page-gutter) var(--space-3);
}

.site-header__menu {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    min-height: var(--control-height);
    padding: 0 var(--space-5) 0 var(--space-4);
    font-family: var(--font-ui);
    font-size: 1rem;
    font-weight: 600;
    color: var(--color-on-forest);
    background: transparent;
    border: 0.125rem solid var(--color-on-forest);
    border-radius: var(--radius-pill);
    cursor: pointer;
}

.site-header__menu:hover,
.site-header__menu[aria-expanded='true'] {
    background: color-mix(in oklch, var(--color-on-forest) 14%, transparent);
}

.site-header__menu-icon {
    width: 1.125rem;
    height: 1.125rem;
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
    padding: 0.25rem var(--space-4) 0;
    font-family: var(--font-ui);
    font-size: 1rem;
    font-weight: 500;
    color: var(--color-on-forest-muted);
    text-decoration: none;
    transition: color var(--transition), box-shadow var(--transition);
}

.site-header__link:hover,
.site-header__link--current {
    color: var(--color-on-forest);
    box-shadow: inset 0 0.25rem 0 var(--color-leaf);
}

@media (forced-colors: active) {
    .site-header__link--current {
        text-decoration: underline;
    }
}

@media (min-width: 40rem) {
    .site-header__bar {
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        grid-template-areas: 'languages home end';
    }
}

@media (min-width: 56rem) {
    .site-header__bar {
        padding-top: var(--space-5);
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
        gap: var(--space-3);
    }

    .site-header__link {
        padding-inline: var(--space-4);
    }
}
</style>
