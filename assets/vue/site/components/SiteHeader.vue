<script setup>
import { ChevronDown, Menu } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import LanguageSwitch from './LanguageSwitch.vue';

defineProps({
    home: { type: String, required: true },
    studioName: { type: String, required: true },
    logo: { type: Object, default: null },
    navigation: { type: Array, required: true },
    languages: { type: Array, required: true },
});

const open = ref(false);
const shopOpen = ref(false);
let shopEntry = null;
const keepShopEntry = (element) => {
    shopEntry = element;
};
const closeShop = (event) => {
    if (!shopOpen.value) return;
    shopOpen.value = false;
    event.currentTarget.querySelector('[data-shop-menu]').focus();
};
const leaveShop = (event) => {
    if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) shopOpen.value = false;
};
const pressOutside = (event) => {
    if (shopOpen.value && !shopEntry?.contains(event.target)) shopOpen.value = false;
};

onMounted(() => document.addEventListener('pointerdown', pressOutside));
onBeforeUnmount(() => document.removeEventListener('pointerdown', pressOutside));
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
                <template v-for="entry in navigation" :key="entry.key">
                    <li v-if="entry.children" :ref="keepShopEntry" class="site-header__shop" @keydown.esc="closeShop" @focusout="leaveShop">
                        <button
                            :class="['site-header__link', 'site-header__shop-button', { 'site-header__link--current': entry.current }]"
                            type="button"
                            :aria-expanded="shopOpen"
                            aria-controls="shop-categories"
                            :aria-current="entry.current ? 'true' : undefined"
                            data-shop-menu
                            @click="shopOpen = !shopOpen"
                        >
                            {{ $t('nav.shop') }}
                            <ChevronDown class="site-header__chevron" aria-hidden="true" />
                        </button>
                        <a id="shop-categories-label" :class="['site-header__link', 'site-header__label', { 'site-header__link--current': entry.current }]" :href="entry.url" :aria-current="entry.current ? 'true' : undefined">{{ $t('nav.shop') }}</a>
                        <ul id="shop-categories" aria-labelledby="shop-categories-label" :class="['site-header__categories', { 'site-header__categories--open': shopOpen }]" data-categories>
                            <li v-for="child in entry.children" :key="child.key">
                                <a :class="['site-header__category', { 'site-header__category--current': child.current }]" :href="child.url" :aria-current="child.current ? 'page' : undefined">{{ child.label }}</a>
                            </li>
                        </ul>
                    </li>
                    <li v-else>
                        <a :class="['site-header__link', { 'site-header__link--current': entry.current }]" :href="entry.url" :aria-current="entry.current ? 'page' : undefined">{{ $t(`nav.${entry.key}`) }}</a>
                    </li>
                </template>
            </ul>
        </nav>
    </header>
</template>

<style scoped>
.site-header {
    --deckle: 1.5rem;
    position: relative;
    z-index: 20;
    color: var(--color-on-forest);
    padding-bottom: var(--deckle);
}

.site-header::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    background:
        url('../../../styles/site/paper/tooth.svg') 0 0 / 20rem 20rem,
        var(--color-forest);
    background-blend-mode: multiply, normal;
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
    justify-content: center;
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

.site-header__shop {
    justify-self: stretch;
    display: grid;
    justify-items: center;
}

.site-header__shop-button {
    display: none;
    gap: var(--space-1);
    line-height: inherit;
    background: none;
    border: 0;
    border-radius: 0;
    cursor: pointer;
}

.site-header__chevron {
    width: 1rem;
    height: 1rem;
    transition: rotate var(--transition);
}

.site-header__shop-button[aria-expanded='true'] .site-header__chevron {
    rotate: 180deg;
}

.site-header__categories {
    display: grid;
    width: min(100%, 18rem);
    margin: var(--space-1) 0 var(--space-2);
    padding: var(--space-2) 0;
    color: var(--color-ink);
    background: var(--color-paper);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-sheet);
}

.site-header__category {
    display: flex;
    align-items: center;
    min-height: var(--tap);
    padding: 0 var(--space-5);
    font-family: var(--font-ui);
    font-size: 1rem;
    font-weight: 500;
    line-height: 1.3;
    color: var(--color-ink);
    text-decoration: none;
}

.site-header__category:hover {
    text-decoration: underline;
    text-decoration-thickness: 0.0625rem;
    background: var(--color-wash-leaf);
}

.site-header__category--current {
    font-weight: 700;
    box-shadow: inset 0.25rem 0 0 var(--color-forest);
}

.site-header__category:focus-visible {
    outline-color: var(--color-ink);
    outline-offset: -0.1875rem;
}

@media (forced-colors: active) {
    .site-header__link--current,
    .site-header__category--current {
        text-decoration: underline;
    }

    .site-header__categories {
        border: 0.0625rem solid CanvasText;
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

    .site-header__shop {
        position: relative;
        display: block;
    }

    .site-header__shop-button {
        display: flex;
    }

    .site-header__label {
        display: none;
    }

    .site-header__categories {
        position: absolute;
        top: 100%;
        left: 50%;
        z-index: 1;
        display: none;
        width: max-content;
        min-width: 13rem;
        max-width: calc(100vw - 2 * var(--page-gutter));
        margin: 0;
        translate: -50% 0;
    }

    .site-header__categories--open {
        display: grid;
    }
}
</style>
