<script setup>
defineProps({
    page: { type: String, required: true },
});
</script>

<template>
    <aside class="draft-banner" aria-label="Draft preview">
        <p class="draft-banner__text"><strong>Draft</strong> Only admins see this page. Visitors keep the published version until you publish.</p>
        <nav class="draft-banner__links">
            <a :class="['draft-banner__link', { 'draft-banner__link--current': page === 'full' }]" href="/beta/" :aria-current="page === 'full' ? 'page' : undefined">Full page</a>
            <a :class="['draft-banner__link', { 'draft-banner__link--current': page === 'note' }]" href="/beta/note/" :aria-current="page === 'note' ? 'page' : undefined">Growing note</a>
            <a class="draft-banner__link draft-banner__link--admin" href="/admin/">Back to the admin</a>
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
    background: color-mix(in oklch, var(--color-star) 55%, var(--color-card));
    border-bottom: 0.0625rem solid color-mix(in oklch, var(--color-ink) 25%, transparent);
}

.draft-banner__text strong {
    margin-right: var(--space-2);
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
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
