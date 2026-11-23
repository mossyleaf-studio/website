<script setup>
defineProps({
    current: { type: String, required: true },
    links: { type: Object, required: true },
    labels: {
        type: Object,
        default: () => ({
            region: 'Draft preview',
            title: 'Draft',
            text: 'Only admins see this page. Texts, links, images and legal pages wait for “Publish”; products, conventions and shipping are already live.',
            shop: 'Shop',
            note: 'Growing note',
            admin: 'Back to the admin',
        }),
    },
});
</script>

<template>
    <aside class="draft-banner" :aria-label="labels.region">
        <p class="draft-banner__text"><strong>{{ labels.title }}</strong> {{ labels.text }}</p>
        <nav class="draft-banner__links" :aria-label="labels.region">
            <a v-for="key in ['shop', 'note']" :key="key" :class="['draft-banner__link', { 'draft-banner__link--current': current === key }]" :href="links[key]" :aria-current="current === key ? 'page' : undefined">{{ labels[key] }}</a>
            <a class="draft-banner__link draft-banner__link--admin" :href="links.admin">{{ labels.admin }}</a>
        </nav>
    </aside>
</template>

<style scoped>
.draft-banner {
    position: sticky;
    top: 0;
    z-index: 30;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-2) var(--space-5);
    padding: var(--space-2) var(--gutter);
    font-size: var(--font-size-xs);
    font-weight: 400;
    color: var(--color-ink);
    background: var(--color-wash-star);
    border-bottom: 0.0625rem solid color-mix(in oklch, var(--color-ink) 25%, transparent);
}

.draft-banner__text strong {
    margin-right: var(--space-2);
    font-family: var(--font-ui);
    font-weight: 600;
    font-size: var(--font-size-sm);
}

.draft-banner__links {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-4);
}

.draft-banner__link {
    color: var(--color-ink);
    text-underline-offset: 0.2em;
}

.draft-banner__link--current {
    font-weight: 400;
    text-decoration-thickness: 0.125rem;
}

.draft-banner__link--admin {
    font-weight: 400;
}
</style>
