<script setup>
import { ExternalLink } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseCard from '../../components/ui/BaseCard.vue';
import LocalizedField from '../../components/ui/LocalizedField.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import { ApiError } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { dateFormat } from '../../i18n/locale.js';
import { LIMITS } from '../limits.js';
import { useLegalPages } from '../useShop.js';

const { t, locale } = useI18n();
const toast = useToast();
const { legalPages, load, save } = useLegalPages();

const current = ref(null);
const drafts = reactive({});
const errors = ref({});
const saving = ref(false);

const page = computed(() => legalPages.value.find((candidate) => candidate.kind === current.value));
const empty = computed(() => page.value.text.trim() === '' && page.value.textFr.trim() === '');
const previewLocale = computed(() => (locale.value === 'fr' ? 'fr' : 'en'));

function select(kind) {
    current.value = kind;
    errors.value = {};
    const chosen = legalPages.value.find((candidate) => candidate.kind === kind);
    drafts[kind] ??= { en: chosen?.text ?? '', fr: chosen?.textFr ?? '' };
}

load().then(() => select(legalPages.value[0]?.kind ?? null));

function changedOn(value) {
    return dateFormat({ dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        await save(current.value, { text: drafts[current.value].en, textFr: drafts[current.value].fr });
        toast.success(t('admin.saved'));
    } catch (error) {
        if (error instanceof ApiError && error.violations.length) {
            errors.value = error.fieldErrors;
        } else {
            toast.error(error.message);
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="legal-page">
        <PageHeader :title="t('admin.legal.title')" :subtitle="t('admin.legal.subtitle')" />

        <BaseCard class="legal-page__card">
            <div class="legal-page__tabs" role="group" :aria-label="t('admin.legal.pages')">
                <button
                    v-for="item in legalPages"
                    :key="item.kind"
                    type="button"
                    :class="['legal-page__tab', { 'legal-page__tab--current': item.kind === current }]"
                    :aria-pressed="item.kind === current ? 'true' : 'false'"
                    :data-test="`legal-tab-${item.kind}`"
                    @click="select(item.kind)"
                >{{ t(`admin.legal.kinds.${item.kind}`) }}</button>
            </div>

            <form v-if="page && drafts[current]" class="legal-page__form" data-test="legal-form" @submit.prevent="submit">
                <div class="legal-page__status">
                    <StatusBadge v-if="empty" tone="warning" data-test="legal-hidden">{{ t('admin.legal.hidden') }}</StatusBadge>
                    <p class="legal-page__changed">{{ page.updatedAt ? t('admin.legal.changed', { when: changedOn(page.updatedAt) }) : t('admin.legal.neverChanged') }}</p>
                    <a class="legal-page__preview" :href="page.preview[previewLocale]" target="_blank" rel="noopener" data-test="legal-preview">
                        <ExternalLink size="0.9rem" aria-hidden="true" />{{ t('admin.legal.preview') }}
                    </a>
                </div>
                <LocalizedField
                    v-model="drafts[current]"
                    :label="t(`admin.legal.kinds.${current}`)"
                    name="text"
                    :max="LIMITS.legalText"
                    :rows="18"
                    :hint="t('admin.legal.hint')"
                    :errors="{ en: errors.text, fr: errors.textFr }"
                />
                <div class="actions-row">
                    <BaseButton type="submit" :loading="saving">{{ t('common.save') }}</BaseButton>
                </div>
            </form>
        </BaseCard>
    </div>
</template>

<style scoped>
.legal-page { display: flex; flex-direction: column; gap: var(--space-5); }
.legal-page__card { display: flex; flex-direction: column; gap: var(--space-5); }
.legal-page__tabs { display: flex; flex-wrap: wrap; gap: var(--space-2); }

.legal-page__tab {
    padding: var(--space-2) var(--space-3);
    border: 0.0625rem solid var(--color-border-strong);
    border-radius: var(--radius);
    background: var(--color-surface);
    color: var(--color-muted);
    font-size: var(--font-size-md);
    font-weight: 600;
    cursor: pointer;
}

.legal-page__tab--current { border-color: var(--color-accent); color: var(--color-accent-strong); background: var(--color-accent-soft); }
.legal-page__form { display: flex; flex-direction: column; gap: var(--space-4); }
.legal-page__status { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2) var(--space-4); }
.legal-page__changed { color: var(--color-muted); font-size: var(--font-size-sm); }
.legal-page__preview { display: inline-flex; align-items: center; gap: var(--space-1); margin-left: auto; color: var(--color-muted); font-size: var(--font-size-md); }
</style>
