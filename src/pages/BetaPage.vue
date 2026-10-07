<script setup>
import ArtGallery from '../components/ArtGallery.vue';
import FeaturedArt from '../components/FeaturedArt.vue';
import LinkCard from '../components/LinkCard.vue';
import SketchbookPage from '../components/SketchbookPage.vue';
import StudioHeader from '../components/StudioHeader.vue';
import { links } from '../links.js';
</script>

<template>
    <SketchbookPage>
        <StudioHeader />

        <FeaturedArt />

        <nav aria-label="Find mossyleaf.studio">
            <ul class="links">
                <li>
                    <LinkCard :href="links.etsy" title="Shop on Etsy" description="Prints, stickers and illustrations" tape="leaf" />
                </li>
                <li>
                    <LinkCard :href="links.instagram" rel="me" title="Follow on Instagram" description="New drawings and market dates" tape="blossom" />
                </li>
            </ul>
        </nav>

        <ArtGallery />

        <section class="about" aria-labelledby="about-title">
            <h2 id="about-title" class="about__title">About the studio</h2>
            <p class="about__text">
                mossyleaf.studio is a tiny illustration studio. It draws the small animals that live among leaves, ferns and moss,
                then turns them into prints, stickers and illustrations.
            </p>
            <p class="about__text">You can order them on Etsy, or find the studio's table at markets and conventions.</p>
        </section>
    </SketchbookPage>
</template>

<style scoped>
.links {
    display: grid;
    gap: var(--space-6);
}

.about {
    display: grid;
    gap: var(--space-4);
}

.about__title {
    font-family: var(--font-display);
    font-weight: 400;
    font-size: var(--font-size-xl);
    color: var(--color-leaf-deep);
}

.about__text {
    color: var(--color-muted);
}
</style>
