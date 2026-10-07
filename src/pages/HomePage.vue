<script setup>
import FeaturedArt from '../components/FeaturedArt.vue';
import SketchbookPage from '../components/SketchbookPage.vue';
import StudioHeader from '../components/StudioHeader.vue';
import WashiTape from '../components/WashiTape.vue';
import { links } from '../links.js';
</script>

<template>
    <SketchbookPage>
        <StudioHeader />

        <FeaturedArt />

        <section class="note" aria-labelledby="note-title">
            <WashiTape class="note__tape" tone="blossom" />
            <h2 id="note-title" class="note__title">The website is still growing</h2>
            <p class="note__text">
                New pages are sprouting slowly. Meanwhile, the shop is open on <a :href="links.etsy">Etsy</a> and new drawings
                appear on <a :href="links.instagram" rel="me">Instagram</a>.
            </p>
        </section>
    </SketchbookPage>
</template>

<style scoped>
.note {
    position: relative;
    display: grid;
    gap: var(--space-3);
    padding: var(--space-6) var(--space-5) var(--space-5);
    background: var(--color-card);
    border: 0.0625rem solid var(--color-line);
    border-radius: 0.1875rem;
    box-shadow: 0 0.0625rem 0.125rem var(--color-shadow), 0 0.75rem 1.5rem -0.75rem var(--color-shadow);
}

.note__tape {
    position: absolute;
    top: -0.75rem;
    left: 50%;
    translate: -50% 0;
    rotate: 2deg;
}

.note__title {
    font-family: var(--font-display);
    font-weight: 400;
    font-size: var(--font-size-xl);
    line-height: 1.2;
    color: var(--color-leaf-deep);
}

.note__text {
    color: var(--color-muted);
}

.note__text a {
    color: var(--color-accent-strong);
    text-decoration-thickness: 0.0625rem;
    text-underline-offset: 0.2em;
}

.note__text a:hover {
    text-decoration-thickness: 0.125rem;
}
</style>
