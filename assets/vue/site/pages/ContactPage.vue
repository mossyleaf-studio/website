<script setup>
import ContactForm from '../components/ContactForm.vue';
import LinkCard from '../components/LinkCard.vue';
import PaperSheet from '../components/PaperSheet.vue';
import { relOf } from '../links.js';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <article :class="['contact', { 'contact--with-form': data.endpoint }]">
        <header class="contact__header">
            <h1 class="contact__title">{{ data.title }}</h1>
            <p class="contact__intro" data-intro>{{ $t('contact.intro') }}</p>
        </header>
        <PaperSheet v-if="data.endpoint" tag="section" tape="leaf" class="contact__sheet" aria-labelledby="contact-form-title">
            <h2 id="contact-form-title" class="contact__sheet-title">{{ $t('contact.form.title') }}</h2>
            <ContactForm :endpoint="data.endpoint" />
        </PaperSheet>
        <ul v-if="data.links.length" class="contact__links" :aria-label="$t('contact.links')" data-links data-test="contact-links">
            <li v-for="link in data.links" :key="link.id" class="contact__link">
                <LinkCard :href="link.url" :title="link.title" :description="link.description" :tape="link.tape" :rel="relOf(link.url)" />
            </li>
        </ul>
    </article>
</template>

<style scoped>
.contact {
    display: grid;
    gap: var(--space-7);
    align-items: start;
}

.contact__header {
    display: grid;
    gap: var(--space-3);
}

.contact__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2.125rem, 1.5rem + 2.5vw, var(--font-size-3xl));
    line-height: 1.08;
}

.contact__intro {
    max-width: 44ch;
    font-size: var(--font-size-lg);
    line-height: 1.4;
}

.contact__sheet {
    width: min(100%, 44rem);
}

.contact__sheet-title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-xl);
    line-height: 1.1;
}

.contact__links {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 17rem), 1fr));
    gap: var(--space-6) var(--grid-gap);
    padding-top: var(--space-3);
}

.contact__link {
    display: grid;
    rotate: -0.5deg;
}

.contact__link:nth-child(even) {
    rotate: 0.5deg;
}

.contact__link :deep(.card) {
    align-content: start;
}

@media (min-width: 56rem) {
    .contact--with-form {
        grid-template-columns: minmax(0, 7fr) minmax(0, 4fr);
        column-gap: var(--space-8);
    }

    .contact--with-form .contact__header {
        grid-column: 1 / -1;
    }

    .contact--with-form .contact__links {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
