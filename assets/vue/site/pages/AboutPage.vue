<script setup>
import ArtGallery from '../components/ArtGallery.vue';
import FeaturedArt from '../components/FeaturedArt.vue';
import RichText from '../components/RichText.vue';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <article class="about">
        <h1 class="about__title">{{ data.title }}</h1>
        <div :class="['about__body', { 'about__body--with-art': data.featured }]">
            <div class="about__text" data-paragraphs>
                <p v-for="(paragraph, index) in data.paragraphs" :key="index"><RichText :text="paragraph" /></p>
            </div>
            <FeaturedArt v-if="data.featured" class="about__art" :artwork="data.featured" />
        </div>
        <ArtGallery v-if="data.gallery.artworks.length" class="about__gallery" :title="data.gallery.title" :artworks="data.gallery.artworks" />
    </article>
</template>

<style scoped>
.about {
    display: grid;
    gap: var(--space-7);
}

.about__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2.125rem, 1.5rem + 2.5vw, var(--font-size-3xl));
    line-height: 1.08;
}

.about__body {
    display: grid;
    gap: var(--space-7);
    align-items: start;
}

.about__text {
    display: grid;
    gap: var(--space-4);
    max-width: var(--measure);
}

.about__art {
    width: min(100%, 32rem);
    justify-self: center;
    rotate: -1deg;
}

.about__art :deep(img),
.about__gallery :deep(img) {
    border-radius: var(--radius-photo);
}

@media (min-width: 56rem) {
    .about__body--with-art {
        grid-template-columns: minmax(0, 38rem) minmax(0, 1fr);
        gap: var(--space-8);
    }
}
</style>
