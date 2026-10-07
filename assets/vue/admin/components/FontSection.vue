<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import '../../../styles/site/typefaces.css';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseSelect from '../../components/ui/BaseSelect.vue';
import FormField from '../../components/ui/FormField.vue';
import { useToast } from '../../composables/useToast.js';
import { FONTS } from '../fonts.js';
import { useTexts } from '../useContent.js';

const props = defineProps({
    values: { type: Object, required: true },
});

const { t } = useI18n();
const toast = useToast();
const { save } = useTexts();

const form = reactive({ heading: '', body: '' });
const saving = ref(false);

function reset() {
    form.heading = props.values.headingFont;
    form.body = props.values.bodyFont;
}

watch(() => [props.values.headingFont, props.values.bodyFont], reset, { immediate: true });

const changed = computed(() => form.heading !== props.values.headingFont || form.body !== props.values.bodyFont);

async function submit() {
    saving.value = true;
    await toast.attempt(() => save('fonts', { ...form }), t('admin.saved'));
    saving.value = false;
}
</script>

<template>
    <form class="font-section" data-test="section-fonts" @submit.prevent="submit">
        <header class="font-section__header">
            <h2 class="font-section__title">{{ t('admin.texts.fonts.title') }}</h2>
            <a class="font-section__preview" href="/beta/" target="_blank" rel="noopener">{{ t('admin.texts.seeBeta') }}</a>
        </header>
        <div class="font-section__fields">
            <FormField :label="t('admin.texts.fonts.heading')" as="div">
                <BaseSelect v-model="form.heading" :options="FONTS" name="heading" />
            </FormField>
            <FormField :label="t('admin.texts.fonts.body')" as="div">
                <BaseSelect v-model="form.body" :options="FONTS" name="body" />
            </FormField>
        </div>
        <div class="font-section__sample" :data-heading-font="form.heading" :data-body-font="form.body" :aria-label="t('admin.texts.fonts.sample')" role="figure">
            <p class="font-section__sample-heading">{{ values.studioName }}</p>
            <p class="font-section__sample-body">{{ values.intro }}</p>
        </div>
        <div class="font-section__actions">
            <BaseButton v-if="changed" variant="ghost" @click="reset">{{ t('common.cancel') }}</BaseButton>
            <BaseButton type="submit" :loading="saving" :disabled="!changed">{{ t('common.save') }}</BaseButton>
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

.font-section__sample-heading { font-family: var(--font-display); font-weight: var(--font-display-weight); font-size: 2.2rem; line-height: 1.1; }
.font-section__sample-body { font-family: var(--font-body); font-weight: var(--font-body-weight); font-size: 1.1875rem; line-height: 1.6; }
.font-section__actions { display: flex; justify-content: flex-end; gap: var(--space-2); }
</style>
