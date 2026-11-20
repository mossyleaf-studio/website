<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import FormField from '../../components/ui/FormField.vue';
import LocalizedField from '../../components/ui/LocalizedField.vue';
import { ApiError } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { conventionForm, conventionPayload } from '../conventions.js';
import { LIMITS } from '../limits.js';
import { useConventions } from '../useShop.js';

const props = defineProps({
    convention: { type: Object, default: null },
});

const open = defineModel('open', { type: Boolean, required: true });

const { t } = useI18n();
const toast = useToast();
const conventions = useConventions();

const form = reactive(conventionForm());
const errors = ref({});
const saving = ref(false);

watch(open, (isOpen) => {
    if (!isOpen) return;
    errors.value = {};
    Object.assign(form, conventionForm(props.convention));
});

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        if (props.convention) {
            await conventions.edit(props.convention.id, conventionPayload(form));
        } else {
            await conventions.create(conventionPayload(form));
        }
        await conventions.load();
        open.value = false;
        toast.success(t('admin.saved'));
    } catch (error) {
        if (error instanceof ApiError && error.violations.length) {
            errors.value = error.fieldErrors;
        } else if (error instanceof ApiError && error.status === 422) {
            errors.value = { endsOn: error.message };
        } else {
            toast.error(error.message);
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BaseModal v-model:open="open" :title="convention ? t('admin.conventions.editTitle') : t('admin.conventions.newTitle')">
        <form class="convention-editor" data-test="convention-editor" @submit.prevent="submit">
            <FormField :label="t('admin.conventions.fields.name')" :error="errors.name">
                <input v-model="form.name" type="text" name="name" :maxlength="LIMITS.conventionName" required />
            </FormField>
            <FormField :label="t('admin.conventions.fields.place')" :error="errors.place">
                <input v-model="form.place" type="text" name="place" :maxlength="LIMITS.conventionPlace" required />
            </FormField>
            <div class="convention-editor__dates">
                <FormField :label="t('admin.conventions.fields.startsOn')" :error="errors.startsOn">
                    <input v-model="form.startsOn" type="date" name="startsOn" required />
                </FormField>
                <FormField :label="t('admin.conventions.fields.endsOn')" :error="errors.endsOn">
                    <input v-model="form.endsOn" type="date" name="endsOn" :min="form.startsOn || null" required />
                </FormField>
            </div>
            <FormField v-slot="{ describedBy }" :label="t('admin.conventions.fields.url')" :hint="t('admin.conventions.urlHint')" :error="errors.url">
                <input v-model="form.url" :aria-describedby="describedBy" type="url" name="url" inputmode="url" :maxlength="LIMITS.conventionUrl" placeholder="https://" />
            </FormField>
            <LocalizedField v-model="form.note" :label="t('admin.conventions.fields.note')" name="note" :max="LIMITS.conventionNote" :rows="3" :errors="{ en: errors.note, fr: errors.noteFr }" />
            <div class="actions-row">
                <BaseButton variant="ghost" @click="open = false">{{ t('common.cancel') }}</BaseButton>
                <BaseButton type="submit" :loading="saving">{{ t('common.save') }}</BaseButton>
            </div>
        </form>
    </BaseModal>
</template>

<style scoped>
.convention-editor { display: flex; flex-direction: column; gap: var(--space-4); }
.convention-editor__dates { display: grid; gap: var(--space-4); grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); }
</style>
