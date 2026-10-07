<script setup>
import { computed, reactive, ref, watch, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import '@fontsource/gaegu/latin-700.css';
import '@fontsource/kalam/latin-300.css';
import BaseButton from '../../components/ui/BaseButton.vue';
import FormField from '../../components/ui/FormField.vue';
import { useToast } from '../../composables/useToast.js';
import { useTexts } from '../useContent.js';

const props = defineProps({
    values: { type: Object, required: true },
});

const { t } = useI18n();
const toast = useToast();
const { chooseFont, fontFamilies, loadFontFamilies } = useTexts();

const ROLES = {
    heading: { source: 'headingFont', site: 'Gaegu', siteWeight: 700, weight: 700 },
    body: { source: 'bodyFont', site: 'Kalam', siteWeight: 300, weight: 400 },
};

const form = reactive({ heading: '', body: '' });
const saving = ref(false);

loadFontFamilies().catch(() => {});

const saved = (role) => props.values[ROLES[role].source]?.family ?? '';

function reset() {
    Object.keys(ROLES).forEach((role) => { form[role] = saved(role); });
}

Object.keys(ROLES).forEach((role) => {
    watch(() => saved(role), (family) => { form[role] = family; }, { immediate: true });
});

const changedRoles = computed(() => Object.keys(ROLES).filter((role) => form[role].trim() !== saved(role)));

function googleStylesheet(family) {
    return `https://fonts.googleapis.com/css2?family=${encodeURIComponent(family).replace(/%20/g, '+')}:wght@300;400;700&display=swap`;
}

const samples = computed(() => Object.fromEntries(Object.entries(ROLES).map(([role, settings]) => {
    const family = form[role].trim();
    const current = props.values[settings.source];
    if (family === '') return [role, { family: settings.site, weight: settings.siteWeight, stylesheet: null }];
    if (current && current.family === family) return [role, current];
    return [role, { family, weight: settings.weight, stylesheet: googleStylesheet(family) }];
})));

watchEffect(() => {
    Object.values(samples.value).forEach(({ stylesheet }) => {
        if (!stylesheet || document.head.querySelector(`link[href="${CSS.escape(stylesheet)}"]`)) return;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = stylesheet;
        document.head.append(link);
    });
});

const sampleStyle = (role) => ({ fontFamily: `'${samples.value[role].family}', cursive`, fontWeight: samples.value[role].weight });

async function submit() {
    saving.value = true;
    let done = true;
    for (const [role, family] of changedRoles.value.map((changed) => [changed, form[changed].trim()])) {
        done = await toast.attempt(() => chooseFont(role, family));
        if (!done) break;
    }
    if (done) toast.success(t('admin.saved'));
    saving.value = false;
}
</script>

<template>
    <form class="font-section" data-test="section-fonts" @submit.prevent="submit">
        <header class="font-section__header">
            <h2 class="font-section__title">{{ t('admin.texts.fonts.title') }}</h2>
            <a class="font-section__preview" href="/beta/" target="_blank" rel="noopener">{{ t('admin.texts.seeBeta') }}</a>
        </header>
        <p class="font-section__hint">
            {{ t('admin.texts.fonts.hint') }}
            <a href="https://fonts.google.com/" target="_blank" rel="noopener">{{ t('admin.texts.fonts.browse') }}</a>
        </p>
        <div class="font-section__fields">
            <FormField v-for="(settings, role) in ROLES" :key="role" :label="t(`admin.texts.fonts.${role}`)" :hint="t('admin.texts.fonts.empty', { font: settings.site })">
                <input v-model="form[role]" type="text" :name="role" list="google-font-families" :placeholder="settings.site" autocomplete="off" spellcheck="false" />
            </FormField>
        </div>
        <datalist id="google-font-families">
            <option v-for="family in fontFamilies" :key="family" :value="family" />
        </datalist>
        <div class="font-section__sample" role="figure" :aria-label="t('admin.texts.fonts.sample')">
            <p class="font-section__sample-heading" :style="sampleStyle('heading')">{{ values.studioName }}</p>
            <p class="font-section__sample-body" :style="sampleStyle('body')">{{ values.intro }}</p>
        </div>
        <div class="font-section__actions">
            <BaseButton v-if="changedRoles.length" variant="ghost" @click="reset">{{ t('common.cancel') }}</BaseButton>
            <BaseButton type="submit" :loading="saving" :disabled="!changedRoles.length">{{ t('common.save') }}</BaseButton>
        </div>
    </form>
</template>

<style scoped>
.font-section {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
    padding: var(--space-5);
    border: 0.0625rem solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
}

.font-section__header { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: var(--space-2); }
.font-section__title { font-family: var(--font-display); font-size: 1.25rem; font-weight: 400; }
.font-section__preview { color: var(--color-muted); font-size: var(--font-size-md); }
.font-section__hint { color: var(--color-muted); font-size: var(--font-size-md); }

.font-section__fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: var(--space-4); }

.font-section__sample {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
    padding: var(--space-4) var(--space-5);
    border: 0.0625rem dashed var(--color-border-strong);
    border-radius: var(--radius);
    background: #fef9e7;
    color: #583e2f;
}

.font-section__sample-heading { font-size: 2.2rem; line-height: 1.1; }
.font-section__sample-body { font-size: 1.1875rem; line-height: 1.6; }
.font-section__actions { display: flex; justify-content: flex-end; gap: var(--space-2); }
</style>
