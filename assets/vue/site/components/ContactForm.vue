<script setup>
import { computed, nextTick, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError } from '../../composables/apiError.js';
import { postJson } from '../api.js';
import { CONTACT_LIMITS, characters, contactErrors, contactPayload, serverErrors } from '../contactForm.js';
import Notice from './Notice.vue';
import PillButton from './PillButton.vue';
import SiteField from './SiteField.vue';

const props = defineProps({
    endpoint: { type: String, required: true },
});

const { t } = useI18n();
const form = reactive({ name: '', email: '', message: '', website: '' });
const errors = ref({});
const failure = ref(null);
const sending = ref(false);
const sentTo = ref(null);
const sentNotice = ref(null);

const id = (field) => `contact-${field}`;
const errorText = (field) => {
    const error = errors.value[field];
    if (!error) return null;
    return error.text ?? t(`form.${error.key}`, { max: error.max });
};
const count = computed(() => t('contact.form.count', { count: characters(form.message), max: CONTACT_LIMITS.message }));

async function showErrors(found) {
    errors.value = found;
    await nextTick();
    const first = Object.keys(CONTACT_LIMITS).find((field) => field in found);
    if (first) document.getElementById(id(first))?.focus();
}

function failureOf(error) {
    if (error instanceof ApiError && error.status === 429) return 'tooMany';
    if (error instanceof ApiError && error.status === 404) return 'closed';
    return 'other';
}

async function submit() {
    if (sending.value) return;
    failure.value = null;
    const found = contactErrors(form);
    if (Object.keys(found).length) {
        await showErrors(found);
        return;
    }

    errors.value = {};
    sending.value = true;
    try {
        const payload = contactPayload(form);
        await postJson(props.endpoint, payload);
        sentTo.value = payload.email;
        await nextTick();
        sentNotice.value?.$el.focus();
    } catch (error) {
        const fields = error instanceof ApiError && error.status === 422 ? serverErrors(error.violations) : {};
        if (Object.keys(fields).length) await showErrors(fields);
        else failure.value = failureOf(error);
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <div class="contact-form">
        <Notice v-if="sentTo" ref="sentNotice" tone="success" :title="$t('contact.sent.title')" tabindex="-1" data-test="contact-sent">
            <p>{{ $t('contact.sent.text', { email: sentTo }) }}</p>
        </Notice>
        <form v-else class="contact-form__form" novalidate :aria-busy="sending ? 'true' : undefined" data-test="contact-form" @submit.prevent="submit">
            <SiteField v-slot="{ control }" :id="id('name')" :label="$t('contact.form.name')" :error="errorText('name')">
                <input v-bind="control" v-model="form.name" type="text" name="name" required autocomplete="name" inputmode="text" autocapitalize="words" spellcheck="false">
            </SiteField>
            <SiteField v-slot="{ control }" :id="id('email')" :label="$t('contact.form.email')" :hint="$t('contact.form.emailHint')" :error="errorText('email')">
                <input v-bind="control" v-model="form.email" type="email" name="email" required autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false">
            </SiteField>
            <SiteField v-slot="{ control }" :id="id('message')" :label="$t('contact.form.message')" :hint="count" :error="errorText('message')">
                <textarea v-bind="control" v-model="form.message" name="message" rows="8" required autocomplete="off" inputmode="text" />
            </SiteField>
            <div class="contact-form__trap" aria-hidden="true">
                <label :for="id('website')">{{ $t('contact.form.website') }}</label>
                <input :id="id('website')" v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>
            <div class="contact-form__actions">
                <Notice v-if="failure" tone="error" role="alert" data-test="contact-failure">
                    <p>{{ $t(`contact.failure.${failure}`) }}</p>
                </Notice>
                <PillButton type="submit" :disabled="sending" data-test="contact-send">
                    {{ sending ? $t('contact.form.sending') : $t('contact.form.send') }}
                </PillButton>
            </div>
        </form>
    </div>
</template>

<style scoped>
.contact-form__form {
    display: grid;
    gap: var(--space-5);
}

.contact-form__trap {
    position: absolute;
    width: 0.0625rem;
    height: 0.0625rem;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
}

.contact-form__actions {
    display: grid;
    gap: var(--space-4);
    justify-items: start;
    padding-top: var(--space-1);
}
</style>
