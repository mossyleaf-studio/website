<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import FormField from '../../components/ui/FormField.vue';
import { ApiError } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { useTexts } from '../useContent.js';

const props = defineProps({
    title: { type: String, required: true },
    section: { type: String, required: true },
    fields: { type: Array, required: true },
    values: { type: Object, required: true },
    preview: { type: Object, default: null },
});

const { t } = useI18n();
const toast = useToast();
const { save } = useTexts();

const form = reactive({});
const errors = ref({});
const saving = ref(false);

function reset() {
    props.fields.forEach((field) => { form[field.name] = props.values[field.source] ?? ''; });
}

watch(() => JSON.stringify(props.fields.map((field) => props.values[field.source] ?? '')), reset, { immediate: true });

const changed = computed(() => props.fields.some((field) => form[field.name] !== (props.values[field.source] ?? '')));

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        await save(props.section, { ...form });
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
    <form class="text-section" :data-test="`section-${section}`" @submit.prevent="submit">
        <header class="text-section__header">
            <h2 class="text-section__title">{{ title }}</h2>
            <a v-if="preview" class="text-section__preview" :href="preview.href" target="_blank" rel="noopener">{{ preview.label }}</a>
        </header>
        <FormField
            v-for="field in fields"
            :key="field.name"
            :label="field.label"
            :error="errors[field.name]"
            :hint="[field.hint, t('admin.count', { count: (form[field.name] ?? '').length, max: field.max })].filter(Boolean).join(' · ')"
        >
            <textarea v-if="field.rows" v-model="form[field.name]" :rows="field.rows" :maxlength="field.max" :name="field.name" />
            <input v-else v-model="form[field.name]" type="text" :maxlength="field.max" :name="field.name" />
        </FormField>
        <div class="text-section__actions">
            <BaseButton v-if="changed" variant="ghost" @click="reset">{{ t('common.cancel') }}</BaseButton>
            <BaseButton type="submit" :loading="saving" :disabled="!changed">{{ t('common.save') }}</BaseButton>
        </div>
    </form>
</template>

<style scoped>
.text-section {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
    padding: var(--space-5);
    border: 0.0625rem solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
}

.text-section__header { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: var(--space-2); }
.text-section__title { font-family: var(--font-display); font-size: 1.25rem; font-weight: 400; }
.text-section__preview { color: var(--color-muted); font-size: var(--font-size-md); }
.text-section__actions { display: flex; justify-content: flex-end; gap: var(--space-2); }
</style>
