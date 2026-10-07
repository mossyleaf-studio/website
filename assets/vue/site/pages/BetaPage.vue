<script setup>
import ArtGallery from '../components/ArtGallery.vue';
import FeaturedArt from '../components/FeaturedArt.vue';
import LinkCard from '../components/LinkCard.vue';
import RichText from '../components/RichText.vue';
import SketchbookPage from '../components/SketchbookPage.vue';
import StudioHeader from '../components/StudioHeader.vue';

defineProps({
    site: { type: Object, required: true },
});

const SOCIAL_HOSTS = ['instagram.com', 'www.instagram.com'];

function relOf(url) {
    return SOCIAL_HOSTS.includes(new URL(url).hostname) ? 'me' : undefined;
}
</script>

<template>
    <SketchbookPage>
        <StudioHeader :name="site.studioName" :intro="site.intro" :logo="site.logo" />

        <FeaturedArt v-if="site.featured" :artwork="site.featured" />

        <nav v-if="site.links.length" :aria-label="`Find ${site.studioName}`">
            <ul class="links">
                <li v-for="link in site.links" :key="link.id">
                    <LinkCard :href="link.url" :rel="relOf(link.url)" :title="link.title" :description="link.description" :tape="link.tape" />
                </li>
            </ul>
        </nav>

        <ArtGallery v-if="site.gallery.artworks.length" :title="site.gallery.title" :artworks="site.gallery.artworks" />

        <section class="about" aria-labelledby="about-title">
            <h2 id="about-title" class="about__title">{{ site.about.title }}</h2>
            <p v-for="(paragraph, index) in site.about.paragraphs" :key="index" class="about__text"><RichText :text="paragraph" /></p>
        </section>
    </SketchbookPage>
</template>

<style scoped>
.links {
    display: grid;
    gap: var(--space-6);
    margin: 0;
    padding: 0;
    list-style: none;
}

.about {
    display: grid;
    gap: var(--space-4);
}

.about__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-xl);
    color: var(--color-ink);
}

.about__text {
    color: var(--color-muted);
}
</style>
