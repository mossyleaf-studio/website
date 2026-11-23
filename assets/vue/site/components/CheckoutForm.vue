<script setup>
import { CircleAlert, LockKeyhole } from '@lucide/vue';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { checkoutErrors, emptyDelivery, firstInvalid, needsRegion } from '../checkoutValidation.js';
import { formatPrice } from '../money.js';
import PillButton from './PillButton.vue';
import SiteField from './SiteField.vue';

const props = defineProps({
    countries: { type: Array, required: true },
    termsUrl: { type: String, default: null },
    zone: { type: String, default: null },
    total: { type: Number, default: null },
    busy: { type: Boolean, default: false },
    sending: { type: Boolean, default: false },
});

const country = defineModel('country', { type: String, default: null });
const emit = defineEmits(['submit']);

const { t, locale } = useI18n();
const form = reactive(emptyDelivery());
const errors = ref({});

watch(
    () => ({ ...form, country: country.value }),
    (current, previous) => {
        const changed = Object.keys(current).filter((field) => current[field] !== previous[field] && field in errors.value);
        if (changed.length) errors.value = Object.fromEntries(Object.entries(errors.value).filter(([field]) => !changed.includes(field)));
    },
);

const id = (field) => `checkout-${field}`;
const region = computed(() => needsRegion(props.zone));
const errorText = (field) => {
    const error = errors.value[field];
    if (!error) return null;
    return error.text ?? t(error.key, { max: error.max });
};
const payLabel = computed(() => {
    if (props.sending) return t('checkout.paying');
    if (props.total === null) return t('checkout.loading');
    return t('checkout.pay', { amount: formatPrice(props.total, locale.value) });
});

async function showErrors(found) {
    errors.value = found;
    await nextTick();
    const first = firstInvalid(found);
    if (first) document.getElementById(id(first))?.focus();
}

async function submit() {
    if (props.sending || props.busy) return;
    const found = checkoutErrors(form, { country: country.value, zone: props.zone });
    if (Object.keys(found).length) {
        await showErrors(found);
        return;
    }
    errors.value = {};
    emit('submit', { ...form });
}

defineExpose({ showErrors });
</script>

<template>
    <form class="checkout-form" novalidate :aria-busy="sending ? 'true' : undefined" data-test="checkout-form" @submit.prevent="submit">
        <fieldset class="checkout-form__group">
            <legend class="checkout-form__legend">{{ $t('checkout.contact') }}</legend>
            <SiteField v-slot="{ control }" :id="id('email')" :label="$t('checkout.email')" :hint="$t('checkout.emailHint')" :error="errorText('email')">
                <input v-bind="control" v-model="form.email" type="email" name="email" required autocomplete="email" inputmode="email" enterkeyhint="next" autocapitalize="off" spellcheck="false" />
            </SiteField>
            <SiteField v-slot="{ control }" :id="id('name')" :label="$t('checkout.name')" :error="errorText('name')">
                <input v-bind="control" v-model="form.name" type="text" name="name" required autocomplete="shipping name" inputmode="text" enterkeyhint="next" autocapitalize="words" spellcheck="false" />
            </SiteField>
            <SiteField v-slot="{ control }" :id="id('phone')" :label="$t('checkout.phone')" :hint="$t('checkout.phoneHint')" :error="errorText('phone')">
                <input v-bind="control" v-model="form.phone" type="tel" name="phone" autocomplete="shipping tel" inputmode="tel" enterkeyhint="next" />
            </SiteField>
        </fieldset>

        <fieldset class="checkout-form__group">
            <legend class="checkout-form__legend">{{ $t('checkout.delivery') }}</legend>
            <SiteField v-slot="{ control }" :id="id('line1')" :label="$t('checkout.line1')" :error="errorText('line1')">
                <input v-bind="control" v-model="form.line1" type="text" name="line1" required autocomplete="shipping address-line1" inputmode="text" enterkeyhint="next" />
            </SiteField>
            <SiteField v-slot="{ control }" :id="id('line2')" :label="$t('checkout.line2')" :error="errorText('line2')">
                <input v-bind="control" v-model="form.line2" type="text" name="line2" autocomplete="shipping address-line2" inputmode="text" enterkeyhint="next" />
            </SiteField>
            <div class="checkout-form__row">
                <SiteField v-slot="{ control }" :id="id('postalCode')" class="checkout-form__postal" :label="$t('checkout.postalCode')" :error="errorText('postalCode')">
                    <input v-bind="control" v-model="form.postalCode" type="text" name="postalCode" required autocomplete="shipping postal-code" inputmode="text" enterkeyhint="next" autocapitalize="characters" spellcheck="false" />
                </SiteField>
                <SiteField v-slot="{ control }" :id="id('city')" :label="$t('checkout.city')" :error="errorText('city')">
                    <input v-bind="control" v-model="form.city" type="text" name="city" required autocomplete="shipping address-level2" inputmode="text" enterkeyhint="next" autocapitalize="words" />
                </SiteField>
            </div>
            <SiteField v-slot="{ control }" :id="id('country')" :label="$t('checkout.country')" :error="errorText('country')">
                <select v-bind="control" v-model="country" name="country" required autocomplete="shipping country" data-test="checkout-country">
                    <option v-if="country === null" :value="null" disabled>{{ $t('cart.summary.chooseCountry') }}</option>
                    <option v-for="entry in countries" :key="entry.code" :value="entry.code">{{ entry.name }}</option>
                </select>
            </SiteField>
            <SiteField v-if="region" v-slot="{ control }" :id="id('region')" :label="$t('checkout.region')" :hint="$t('checkout.regionHint')" :error="errorText('region')">
                <input v-bind="control" v-model="form.region" type="text" name="region" required autocomplete="shipping address-level1" inputmode="text" enterkeyhint="next" autocapitalize="words" />
            </SiteField>
        </fieldset>

        <div class="checkout-form__pay">
            <slot name="notice" />
            <template v-if="termsUrl">
                <div :class="['checkout-form__terms', { 'checkout-form__terms--invalid': errors.terms }]">
                    <input :id="id('terms')" v-model="form.terms" class="checkout-form__checkbox" type="checkbox" name="terms" required :aria-invalid="errors.terms ? 'true' : undefined" :aria-describedby="errors.terms ? `${id('terms')}-error` : undefined" data-test="checkout-terms" />
                    <label class="checkout-form__terms-label" :for="id('terms')">
                        <i18n-t keypath="checkout.terms" scope="global">
                            <template #terms>
                                <a :href="termsUrl" target="_blank" rel="noopener" data-test="checkout-terms-link">{{ $t('checkout.termsLink') }}<span class="visually-hidden"> ({{ $t('checkout.newTab') }})</span></a>
                            </template>
                        </i18n-t>
                    </label>
                    <p v-if="errors.terms" :id="`${id('terms')}-error`" class="checkout-form__error" data-test="field-error">
                        <CircleAlert class="checkout-form__error-icon" aria-hidden="true" />
                        <span>{{ errorText('terms') }}</span>
                    </p>
                </div>
                <PillButton class="checkout-form__submit" type="submit" :disabled="sending || busy || total === null" data-test="checkout-pay">{{ payLabel }}</PillButton>
                <p class="checkout-form__note">
                    <LockKeyhole class="checkout-form__note-icon" aria-hidden="true" />
                    <span>{{ $t('checkout.payNote') }}</span>
                </p>
            </template>
            <p v-else class="checkout-form__closed" data-test="checkout-no-terms">{{ $t('checkout.termsMissing') }}</p>
        </div>
    </form>
</template>

<style scoped>
.checkout-form {
    display: grid;
    gap: var(--space-7);
}

.checkout-form__group {
    display: grid;
    gap: var(--space-5);
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.checkout-form__legend {
    margin-bottom: var(--space-4);
    padding: 0;
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-xl);
    line-height: 1.1;
}

.checkout-form__row {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 3fr);
    gap: var(--space-4);
    align-items: start;
}

.checkout-form__pay {
    display: grid;
    gap: var(--space-5);
    padding-top: var(--space-5);
    border-top: var(--border-strong);
}

.checkout-form__terms {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: var(--space-2) var(--space-3);
    align-items: start;
    padding: var(--space-3) var(--space-4);
    border: 0.0625rem solid var(--color-edge);
    border-radius: var(--radius-photo);
}

.checkout-form__terms--invalid {
    background: var(--color-fur);
    border: var(--border-strong);
}

.checkout-form__checkbox {
    width: 1.5rem;
    height: 1.5rem;
    margin: var(--space-2) 0 0;
    accent-color: var(--color-ink);
    cursor: pointer;
}

.checkout-form__checkbox:focus-visible {
    outline: 0.1875rem solid var(--color-ink);
    outline-offset: 0.125rem;
}

.checkout-form__terms-label {
    padding-block: var(--space-2);
    line-height: 1.4;
    cursor: pointer;
}

.checkout-form__terms-label a {
    font-weight: 600;
}

.checkout-form__error {
    grid-column: 1 / -1;
    display: flex;
    gap: var(--space-2);
    align-items: start;
    font-size: var(--font-size-sm);
    font-weight: 600;
    line-height: 1.35;
}

.checkout-form__error-icon {
    flex: none;
    width: 1.25rem;
    height: 1.25rem;
    margin-top: 0.0625rem;
    stroke-width: 2.25;
}

.checkout-form__submit {
    width: 100%;
    min-height: 3.5rem;
    font-size: var(--font-size-lg);
}

.checkout-form__note {
    display: flex;
    gap: var(--space-2);
    align-items: start;
    max-width: 44ch;
    font-size: var(--font-size-xs);
    line-height: 1.45;
    color: var(--color-muted);
}

.checkout-form__note-icon {
    flex: none;
    width: 1.125rem;
    height: 1.125rem;
    margin-top: 0.125rem;
}

.checkout-form__closed {
    padding: var(--space-4);
    font-size: var(--font-size-sm);
    line-height: 1.45;
    background: var(--color-fur);
    border: var(--border-strong);
    border-radius: var(--radius-photo);
}
</style>
