<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHeader from '../../components/ui/PageHeader.vue';
import LogoSection from '../components/LogoSection.vue';
import TextSection from '../components/TextSection.vue';
import { LIMITS } from '../limits.js';
import { useTexts } from '../useContent.js';

const { t } = useI18n();
const { texts, load } = useTexts();
load();

const sections = computed(() => [
    {
        section: 'identity',
        title: t('admin.texts.identity.title'),
        preview: { href: '/beta/', label: t('admin.texts.seeBeta') },
        fields: [
            { name: 'studioName', source: 'studioName', label: t('admin.texts.identity.studioName'), max: LIMITS.studioName },
            { name: 'intro', source: 'intro', label: t('admin.texts.identity.intro'), max: LIMITS.intro, rows: 2 },
            { name: 'searchTitle', source: 'searchTitle', label: t('admin.texts.identity.searchTitle'), max: LIMITS.searchTitle, hint: t('admin.texts.identity.searchTitleHint') },
            { name: 'metaDescription', source: 'metaDescription', label: t('admin.texts.identity.metaDescription'), max: LIMITS.metaDescription, rows: 3 },
        ],
    },
    {
        section: 'home-note',
        title: t('admin.texts.homeNote.title'),
        preview: { href: '/beta/note/', label: t('admin.texts.seeHome') },
        fields: [
            { name: 'title', source: 'homeNoteTitle', label: t('admin.texts.homeNote.noteTitle'), max: LIMITS.title },
            { name: 'text', source: 'homeNoteText', label: t('admin.texts.homeNote.text'), max: LIMITS.homeNote, rows: 5, hint: t('admin.texts.linksHint') },
        ],
    },
    {
        section: 'about',
        title: t('admin.texts.about.title'),
        preview: { href: '/beta/', label: t('admin.texts.seeBeta') },
        fields: [
            { name: 'title', source: 'aboutTitle', label: t('admin.texts.about.aboutTitle'), max: LIMITS.title },
            { name: 'text', source: 'aboutText', label: t('admin.texts.about.text'), max: LIMITS.about, rows: 8, hint: `${t('admin.texts.paragraphsHint')} ${t('admin.texts.linksHint')}` },
        ],
    },
    {
        section: 'gallery',
        title: t('admin.texts.gallery.title'),
        preview: { href: '/beta/', label: t('admin.texts.seeBeta') },
        fields: [
            { name: 'title', source: 'galleryTitle', label: t('admin.texts.gallery.galleryTitle'), max: LIMITS.title },
        ],
    },
]);
</script>

<template>
    <div class="texts-page">
        <PageHeader :title="t('admin.texts.title')" :subtitle="t('admin.texts.subtitle')" />
        <template v-if="texts">
            <LogoSection :logo="texts.logo" />
            <TextSection v-for="section in sections" :key="section.section" v-bind="section" :values="texts" />
        </template>
    </div>
</template>

<style scoped>
.texts-page { display: flex; flex-direction: column; gap: var(--space-5); }
</style>
