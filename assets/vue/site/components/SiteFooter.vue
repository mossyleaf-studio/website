<script setup>
import { relOf } from '../links.js';
import LanguageSwitch from './LanguageSwitch.vue';

defineProps({
    studioName: { type: String, required: true },
    intro: { type: String, required: true },
    links: { type: Array, required: true },
    legal: { type: Array, default: () => [] },
    languages: { type: Array, required: true },
});

const year = new Date().getFullYear();
</script>

<template>
    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="site-footer__studio">
                <h2 class="site-footer__name">{{ studioName }}</h2>
                <p class="site-footer__intro">{{ intro }}</p>
            </div>
            <nav v-if="links.length" class="site-footer__links" :aria-label="$t('footer.links')" data-links>
                <ul class="site-footer__list">
                    <li v-for="link in links" :key="link.id">
                        <a class="site-footer__link" :href="link.url" :rel="relOf(link.url)">{{ link.title }}</a>
                    </li>
                </ul>
            </nav>
            <nav v-if="legal.length" class="site-footer__links" :aria-label="$t('legal.footer')" data-legal>
                <ul class="site-footer__list">
                    <li v-for="page in legal" :key="page.kind">
                        <a class="site-footer__link" :href="page.url">{{ $t(`legal.title.${page.kind}`) }}</a>
                    </li>
                </ul>
            </nav>
            <LanguageSwitch class="site-footer__languages" :languages="languages" :landmark="false" tone="band" />
            <p class="site-footer__copyright">{{ $t('footer.copyright', { year, studio: studioName }) }}</p>
        </div>
    </footer>
</template>

<style scoped>
.site-footer {
    color: var(--color-ink);
    background: var(--color-bark);
}

.site-footer__inner {
    display: grid;
    gap: var(--space-5);
    max-width: calc(var(--page) + 2 * var(--page-gutter));
    margin-inline: auto;
    padding: var(--space-6) var(--page-gutter) var(--space-5);
}

.site-footer__studio {
    display: grid;
    gap: var(--space-2);
    max-width: 30rem;
}

.site-footer__name {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-lg);
    line-height: 1.1;
}

.site-footer__intro {
    font-size: var(--font-size-sm);
    line-height: 1.5;
}

.site-footer__list {
    display: grid;
    font-size: var(--font-size-sm);
}

.site-footer__link {
    display: inline-flex;
    align-items: center;
    min-height: var(--tap);
    text-underline-offset: 0.25em;
}

.site-footer__link:hover {
    text-decoration-thickness: 0.125rem;
}

.site-footer__languages {
    justify-self: start;
}

.site-footer__copyright {
    padding-top: var(--space-4);
    font-size: var(--font-size-xs);
    border-top: 0.0625rem solid color-mix(in oklch, var(--color-ink) 65%, var(--color-bark));
}

@media (min-width: 56rem) {
    .site-footer__inner {
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto;
        align-items: start;
        column-gap: var(--space-8);
    }

    .site-footer__languages {
        grid-column: -2;
    }

    .site-footer__copyright {
        grid-column: 1 / -1;
    }
}
</style>
