<script setup>
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import FormField from '../../components/ui/FormField.vue';
import { ApiError } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { LIMITS } from '../limits.js';
import { useLinks } from '../useContent.js';

const props = defineProps({
    link: { type: Object, default: null },
});

const open = defineModel('open', { type: Boolean, required: true });

const TAPES = ['leaf', 'blossom'];

const { t } = useI18n();
const toast = useToast();
const links = useLinks();

const form = reactive({ title: '', description: '', url: 'https://', tape: 'leaf' });
const errors = ref({});
const saving = ref(false);

watch(open, (isOpen) => {
    if (!isOpen) return;
    errors.value = {};
    Object.assign(form, props.link
        ? { title: props.link.title, description: props.link.description, url: props.link.url, tape: props.link.tape }
        : { title: '', description: '', url: 'https://', tape: 'leaf' });
});

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        if (props.link) {
            await links.edit(props.link.id, { ...form });
        } else {
            await links.create({ ...form });
        }
        open.value = false;
        toast.success(t('admin.saved'));
    } catch (error) {
        if (error instanceof ApiError && error.violations.length) {
            errors.value = error.fieldErrors;
        } else {
            errors.value = { url: error.message };
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BaseModal v-model:open="open" :title="link ? t('admin.links.editTitle') : t('admin.links.newTitle')">
        <form class="link-editor" data-test="link-editor" @submit.prevent="submit">
            <FormField :label="t('admin.links.fields.title')" :error="errors.title">
                <input v-model="form.title" type="text" name="title" :maxlength="LIMITS.linkTitle" required />
            </FormField>
            <FormField :label="t('admin.links.fields.description')" :error="errors.description">
                <input v-model="form.description" type="text" name="description" :maxlength="LIMITS.linkDescription" required />
            </FormField>
            <FormField :label="t('admin.links.fields.url')" :error="errors.url">
                <input v-model="form.url" type="url" name="url" inputmode="url" :maxlength="LIMITS.linkUrl" required />
            </FormField>
            <FormField :label="t('admin.links.fields.tape')" as="div">
                <RadioGroupRoot v-model="form.tape" class="link-editor__tapes" orientation="horizontal">
                    <RadioGroupItem v-for="tape in TAPES" :key="tape" :value="tape" :class="['link-editor__tape', `link-editor__tape--${tape}`]">
                        <span class="link-editor__swatch" aria-hidden="true" />
                        {{ t(`admin.links.tapes.${tape}`) }}
                    </RadioGroupItem>
                </RadioGroupRoot>
            </FormField>
            <div class="actions-row">
                <BaseButton variant="ghost" @click="open = false">{{ t('common.cancel') }}</BaseButton>
                <BaseButton type="submit" :loading="saving">{{ t('common.save') }}</BaseButton>
            </div>
        </form>
    </BaseModal>
</template>

<style scoped>
.link-editor { display: flex; flex-direction: column; gap: var(--space-4); }
.link-editor__tapes { display: flex; flex-wrap: wrap; gap: var(--space-2); }

.link-editor__tape {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-3);
    border: 0.0625rem solid var(--color-border-strong);
    border-radius: var(--radius);
    background: var(--color-surface);
    font-size: var(--font-size-md);
    cursor: pointer;
}

.link-editor__tape[data-state="checked"] { border-color: var(--color-accent); box-shadow: var(--focus-ring); }

.link-editor__swatch { width: 2rem; height: 0.75rem; border-radius: 0.125rem; }
.link-editor__tape--leaf .link-editor__swatch { background: repeating-linear-gradient(-45deg, #b9d39a 0 0.25rem, #dce9cc 0.25rem 0.5rem); }
.link-editor__tape--blossom .link-editor__swatch { background: radial-gradient(circle, #f7e1e4 0 0.1rem, transparent 0.12rem) 0 0 / 0.4rem 0.4rem, #eec3c9; }
</style>
