<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AnnouncementBar from './AnnouncementBar.vue';
import CartLink from './CartLink.vue';
import DraftBanner from './DraftBanner.vue';
import SiteFooter from './SiteFooter.vue';
import SiteHeader from './SiteHeader.vue';

defineProps({
    layout: { type: Object, required: true },
    home: { type: String, required: true },
    cartCurrent: { type: Boolean, default: false },
});

const { t } = useI18n();
const draft = computed(() => Object.fromEntries(['region', 'title', 'text', 'shop', 'note', 'admin'].map((key) => [key, t(`draft.${key}`)])));
</script>

<template>
    <div :class="['shop-layout', { 'shop-layout--preview': layout.draft }]">
        <a class="shop-layout__skip" href="#main">{{ $t('layout.skip') }}</a>
        <DraftBanner v-if="layout.draft" current="shop" :links="layout.draft" :labels="draft" />
        <AnnouncementBar :text="layout.announcement" />
        <SiteHeader :home="home" :studio-name="layout.studioName" :logo="layout.logo" :navigation="layout.navigation" :languages="layout.languages">
            <template #end>
                <CartLink :url="layout.cartUrl" :current="cartCurrent" />
            </template>
        </SiteHeader>
        <main id="main" class="shop-layout__main" tabindex="-1">
            <slot />
        </main>
        <SiteFooter :studio-name="layout.studioName" :intro="layout.intro" :links="layout.footer.links" :legal="layout.footer.legal" :languages="layout.languages" />
    </div>
</template>

<style scoped>
.shop-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.shop-layout__skip {
    position: absolute;
    top: var(--space-2);
    left: var(--space-2);
    z-index: 40;
    padding: var(--space-2) var(--space-4);
    color: var(--color-ink);
    background: var(--color-paper);
    border: var(--border-strong);
    border-radius: var(--radius-pill);
    translate: 0 -200%;
}

.shop-layout__skip:focus-visible {
    translate: none;
}

.shop-layout__main {
    flex: 1 0 auto;
    width: 100%;
    max-width: calc(var(--page) + 2 * var(--page-gutter));
    margin-inline: auto;
    padding: var(--space-7) var(--page-gutter) var(--space-8);
}

.shop-layout--preview .shop-layout__main {
    scroll-margin-top: 7rem;
}

.shop-layout__main:focus {
    outline: none;
}
</style>
