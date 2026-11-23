<script setup>
import FeaturedArt from '../components/FeaturedArt.vue';
import PaperSheet from '../components/PaperSheet.vue';
import RichText from '../components/RichText.vue';
import SketchbookPage from '../components/SketchbookPage.vue';
import StudioHeader from '../components/StudioHeader.vue';

defineProps({
    site: { type: Object, required: true },
});
</script>

<template>
    <SketchbookPage :studio-name="site.studioName">
        <template #band>
            <StudioHeader :name="site.studioName" />
        </template>

        <div :class="['note__intro', { 'note__intro--with-logo': site.logo }]">
            <img v-if="site.logo" class="note__logo" :src="site.logo.url" :width="site.logo.width" :height="site.logo.height" alt="" />
            <p class="note__lead">{{ site.intro }}</p>
        </div>

        <FeaturedArt v-if="site.featured" :artwork="site.featured" />

        <PaperSheet tag="section" tape="blossom" aria-labelledby="note-title">
            <h2 id="note-title" class="note__title">{{ site.homeNote.title }}</h2>
            <p class="note__text"><RichText :text="site.homeNote.text" /></p>
        </PaperSheet>
    </SketchbookPage>
</template>

<style scoped>
.note__intro {
    display: grid;
    gap: var(--space-5);
}

.note__logo {
    width: auto;
    height: auto;
    max-width: min(60%, 12rem);
    max-height: 7rem;
    object-fit: contain;
    object-position: left center;
}

.note__lead {
    max-width: 32ch;
    font-size: var(--font-size-lg);
    line-height: 1.45;
}

.note__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-2xl);
    line-height: 1.15;
}

.note__text {
    max-width: var(--measure);
    white-space: pre-line;
}

@media (min-width: 40rem) {
    .note__intro--with-logo {
        grid-template-columns: auto minmax(0, 1fr);
        align-items: center;
        column-gap: var(--space-6);
    }

    .note__logo {
        max-width: 9rem;
        max-height: 9rem;
    }
}
</style>
