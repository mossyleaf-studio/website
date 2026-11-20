<script setup>
import FeaturedArt from '../components/FeaturedArt.vue';
import PaperSheet from '../components/PaperSheet.vue';
import PillButton from '../components/PillButton.vue';
import RichText from '../components/RichText.vue';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <div class="home">
        <div :class="['home__hero', { 'home__hero--solo': !data.featured }]">
            <h1 class="home__title">{{ data.intro }}</h1>
            <FeaturedArt v-if="data.featured" class="home__art" :artwork="data.featured" />
        </div>

        <PaperSheet tag="section" tape="blossom" class="home__about" aria-labelledby="about-title">
            <h2 id="about-title" class="home__about-title">{{ data.about.title }}</h2>
            <p class="home__about-text"><RichText :text="data.about.text" /></p>
            <p class="home__about-more">
                <PillButton :href="data.about.url" tone="paper">{{ $t('home.aboutLink') }}</PillButton>
            </p>
        </PaperSheet>
    </div>
</template>

<style scoped>
.home {
    display: grid;
    gap: var(--space-9);
}

.home__hero {
    display: grid;
    gap: var(--space-7);
    align-items: center;
}

.home__title {
    max-width: 20ch;
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2rem, 1.25rem + 2.75vw, 3.375rem);
    line-height: 1.08;
    letter-spacing: -0.01em;
    text-wrap: balance;
}

.home__hero--solo .home__title {
    max-width: 22ch;
    margin-inline: auto;
    text-align: center;
}

.home__art {
    width: min(100%, 40rem);
    justify-self: center;
    rotate: 1.5deg;
}

.home__about {
    width: min(100%, 44rem);
    justify-self: center;
}

.home__about-title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-2xl);
    line-height: 1.15;
}

.home__about-text {
    max-width: var(--measure);
}

.home__art :deep(img) {
    border-radius: var(--radius-photo);
}

@media (min-width: 56rem) {
    .home__hero {
        grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
        gap: var(--space-8);
    }

    .home__hero--solo {
        grid-template-columns: none;
        padding-block: var(--space-8);
    }
}
</style>
