<script setup>
import FeaturedArt from '../components/FeaturedArt.vue';
import RichText from '../components/RichText.vue';
import SketchbookPage from '../components/SketchbookPage.vue';
import StudioHeader from '../components/StudioHeader.vue';
import WashiTape from '../components/WashiTape.vue';

defineProps({
    site: { type: Object, required: true },
});
</script>

<template>
    <SketchbookPage>
        <StudioHeader :name="site.studioName" :intro="site.intro" />

        <FeaturedArt v-if="site.featured" :artwork="site.featured" />

        <section class="note" aria-labelledby="note-title">
            <WashiTape class="note__tape" tone="blossom" />
            <h2 id="note-title" class="note__title">{{ site.homeNote.title }}</h2>
            <p class="note__text"><RichText :text="site.homeNote.text" /></p>
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
    white-space: pre-line;
}
</style>
