<script setup>
import { reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseMoneyField from '../../components/ui/BaseMoneyField.vue';
import BaseSwitch from '../../components/ui/BaseSwitch.vue';
import FormField from '../../components/ui/FormField.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import { ApiError } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { LIMITS } from '../limits.js';
import { useShippingZones } from '../useShop.js';

const { t } = useI18n();
const toast = useToast();
const { shippingZones, load, save } = useShippingZones();

const drafts = reactive({});
const errors = reactive({});
const malformed = reactive({});
const saving = ref(null);

load().then(() => {
    for (const zone of shippingZones.value) {
        drafts[zone.code] = { price: zone.price, freeFrom: zone.freeFrom, enabled: zone.enabled };
        errors[zone.code] = {};
        malformed[zone.code] = { price: false, freeFrom: false };
    }
});

async function submit(code) {
    if (malformed[code].price || malformed[code].freeFrom) {
        return;
    }
    saving.value = code;
    errors[code] = {};
    try {
        await save(code, { ...drafts[code], price: drafts[code].price ?? 0 });
        toast.success(t('admin.saved'));
    } catch (error) {
        if (error instanceof ApiError && error.violations.length) {
            errors[code] = error.fieldErrors;
        } else {
            toast.error(error.message);
        }
    } finally {
        saving.value = null;
    }
}

const amountError = (code, field) => (malformed[code][field] ? t('admin.shipping.invalidAmount') : errors[code][field]);
</script>

<template>
    <div class="shipping-page">
        <PageHeader :title="t('admin.shipping.title')" :subtitle="t('admin.shipping.subtitle')" />
        <p class="shipping-page__hint" data-test="shipping-hint">{{ t('admin.shipping.hint') }}</p>

        <ul class="shipping-page__zones">
            <li v-for="zone in shippingZones" :key="zone.code">
                <form v-if="drafts[zone.code]" class="shipping-zone" :data-test="`zone-${zone.code}`" @submit.prevent="submit(zone.code)">
                    <div class="shipping-zone__head">
                        <div class="shipping-zone__text">
                            <h2 class="shipping-zone__name">{{ t(`admin.shipping.zones.${zone.code}`) }}</h2>
                            <p class="shipping-zone__covers">{{ t(`admin.shipping.covers.${zone.code}`) }}</p>
                        </div>
                        <div class="shipping-zone__switch">
                            <p class="shipping-zone__state" aria-hidden="true">{{ drafts[zone.code].enabled ? t('admin.shipping.on') : t('admin.shipping.off') }}</p>
                            <BaseSwitch v-model="drafts[zone.code].enabled" :label="t('admin.shipping.enabled', { zone: t(`admin.shipping.zones.${zone.code}`) })" :data-test="`zone-${zone.code}-enabled`" />
                        </div>
                    </div>
                    <div class="shipping-zone__fields">
                        <FormField :label="t('admin.shipping.price')" :error="amountError(zone.code, 'price')">
                            <BaseMoneyField v-model="drafts[zone.code].price" v-model:invalid="malformed[zone.code].price" :max="LIMITS.shippingAmount" name="price" :data-test="`zone-${zone.code}-price`" />
                        </FormField>
                        <FormField v-slot="{ describedBy }" :label="t('admin.shipping.freeFrom')" :hint="t('admin.shipping.freeFromHint')" :error="amountError(zone.code, 'freeFrom')">
                            <BaseMoneyField v-model="drafts[zone.code].freeFrom" v-model:invalid="malformed[zone.code].freeFrom" :max="LIMITS.shippingAmount" :aria-describedby="describedBy" name="freeFrom" :data-test="`zone-${zone.code}-free-from`" />
                        </FormField>
                    </div>
                    <div class="actions-row">
                        <BaseButton type="submit" :loading="saving === zone.code" :data-test="`zone-${zone.code}-save`">{{ t('admin.shipping.save', { zone: t(`admin.shipping.zones.${zone.code}`) }) }}</BaseButton>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.shipping-page { display: flex; flex-direction: column; gap: var(--space-4); }
.shipping-page__hint { color: var(--color-muted); }
.shipping-page__zones { display: flex; flex-direction: column; gap: var(--space-3); margin: 0; padding: 0; list-style: none; }

.shipping-zone {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    padding: var(--space-4);
    border: 0.0625rem solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
}

.shipping-zone__head { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); }
.shipping-zone__text { display: flex; flex-direction: column; gap: 0.125rem; min-width: 0; }
.shipping-zone__name { margin: 0; color: var(--color-ink); font-size: var(--font-size-md); font-weight: 600; }
.shipping-zone__covers { color: var(--color-muted); font-size: var(--font-size-md); }
.shipping-zone__switch { display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0; }
.shipping-zone__state { color: var(--color-muted); font-size: var(--font-size-sm); }
.shipping-zone__fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: var(--space-3); }
</style>
