<script setup>
import { useI18n } from 'vue-i18n';
import FieldError from './FieldError.vue';
import FormField from './FormField.vue';

const props = defineProps({
    label: { type: String, required: true },
    name: { type: String, required: true },
    max: { type: Number, required: true },
    rows: { type: Number, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    ownDefault: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const model = defineModel({ type: Object, required: true });

const { t } = useI18n();

const SIDES = [
    { language: 'fr', other: 'en', name: `${props.name}Fr` },
    { language: 'en', other: 'fr', name: props.name },
];

function mandatory(side) {
    return props.required && side.language === 'en';
}

function missing(side) {
    return !mandatory(side) && !(props.ownDefault && side.language === 'en') && model.value[side.language].trim() === '' && model.value[side.other].trim() !== '';
}
</script>

<template>
    <FormField v-slot="{ describedBy }" class="localized-field" :label="label" :hint="hint" as="div">
        <div class="localized-field__sides">
            <label
                v-for="side in SIDES"
                :key="side.language"
                :class="['localized-field__side', { 'localized-field__side--missing': missing(side), 'localized-field__side--invalid': errors[side.language] }]"
            >
                <span class="localized-field__caption">
                    <span>{{ t(`admin.localized.${side.language}`) }}</span>
                    <span class="localized-field__count tabular">{{ t('admin.count', { count: model[side.language].length, max }) }}</span>
                </span>
                <component
                    :is="rows ? 'textarea' : 'input'"
                    class="control"
                    :type="rows ? null : 'text'"
                    :rows="rows"
                    :name="side.name"
                    :lang="side.language"
                    :maxlength="max"
                    :required="mandatory(side)"
                    :aria-label="t('admin.localized.name', { label, language: t(`admin.localized.${side.language}`) })"
                    :aria-describedby="describedBy"
                    :value="model[side.language]"
                    @input="model = { ...model, [side.language]: $event.target.value }"
                />
                <FieldError v-if="errors[side.language]">{{ errors[side.language] }}</FieldError>
                <span v-else-if="missing(side)" class="localized-field__missing" data-test="missing-translation">{{ t(`admin.localized.missing.${side.language}`) }}</span>
            </label>
        </div>
    </FormField>
</template>

<style scoped>
.localized-field { container-type: inline-size; }
.localized-field__sides { display: grid; gap: var(--space-3); }
.localized-field__side { display: flex; flex-direction: column; gap: var(--space-1); min-width: 0; }
.localized-field__caption { display: flex; align-items: baseline; justify-content: space-between; gap: var(--space-2); color: var(--color-muted); font-size: var(--font-size-sm); }
.localized-field__count { color: var(--color-subtle); font-size: var(--font-size-xs); }
.localized-field__missing { color: var(--color-muted); font-size: var(--font-size-sm); }
.localized-field__side--missing .control { border-style: dashed; }
.localized-field__side--invalid .control { border-color: var(--color-danger); }
.localized-field textarea.control { resize: vertical; }

@container (min-width: 40rem) {
    .localized-field__sides { grid-template-columns: 1fr 1fr; }
}
</style>
