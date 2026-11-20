<script setup>
import { PackageCheck, RefreshCw } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import ConfirmButton from '../../components/ui/ConfirmButton.vue';
import FormField from '../../components/ui/FormField.vue';
import { ApiError } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { LIMITS } from '../limits.js';
import { actionsFor } from '../orders.js';
import { useOrders } from '../useOrders.js';

const props = defineProps({
    order: { type: Object, required: true },
});

const { t } = useI18n();
const toast = useToast();
const router = useRouter();
const { ship, cancel, checkPayment, remove } = useOrders();

const actions = computed(() => actionsFor(props.order));
const busy = ref(false);
const shipOpen = ref(false);
const tracking = ref('');
const trackingError = ref(null);
const introId = useId();

function fail(error) {
    toast.error(error instanceof ApiError && error.status === 503 ? t('admin.order.unreachable') : error.message);
}

async function run(action, done) {
    busy.value = true;
    try {
        toast.success(done(await action()));
        return true;
    } catch (error) {
        fail(error);
        return false;
    } finally {
        busy.value = false;
    }
}

async function submitShip() {
    trackingError.value = null;
    busy.value = true;
    try {
        await ship(props.order.id, tracking.value);
        shipOpen.value = false;
        tracking.value = '';
        toast.success(t('admin.order.ship.done', { reference: props.order.reference }));
    } catch (error) {
        if (error instanceof ApiError && error.fieldErrors.trackingNumber) trackingError.value = error.fieldErrors.trackingNumber;
        else fail(error);
    } finally {
        busy.value = false;
    }
}

const cancelOrder = () => run(() => cancel(props.order.id), () => t('admin.order.cancel.done', { reference: props.order.reference }));
const verify = () => run(() => checkPayment(props.order.id), (saved) => t('admin.order.checkPayment.done', { reference: saved.reference, status: t(`admin.orders.status.${saved.status}`) }));

async function deleteOrder() {
    if (await run(() => remove(props.order.id), () => t('admin.order.delete.done', { reference: props.order.reference }))) router.push({ name: 'orders' });
}
</script>

<template>
    <section v-if="actions.length" class="order-actions" data-test="order-actions">
        <h2 class="order-actions__heading">{{ t('admin.order.actions.title') }}</h2>
        <div class="order-actions__row">
            <BaseButton v-if="actions.includes('ship')" :disabled="busy" data-test="order-ship" @click="shipOpen = true"><PackageCheck size="1rem" aria-hidden="true" />{{ t('admin.order.ship.label') }}</BaseButton>
            <BaseButton v-if="actions.includes('checkPayment')" variant="secondary" :loading="busy" data-test="order-check-payment" @click="verify"><RefreshCw size="1rem" aria-hidden="true" />{{ t('admin.order.checkPayment.label') }}</BaseButton>
            <span v-if="actions.includes('cancel')" class="order-actions__confirm" data-test="order-cancel">
                <ConfirmButton variant="danger" :disabled="busy" :label="t('admin.order.cancel.label')" :confirm-label="t('admin.order.cancel.confirm')" :cancel-label="t('admin.order.cancel.keep')" :message="t('admin.order.cancel.message')" @confirm="cancelOrder" />
            </span>
            <span v-if="actions.includes('delete')" class="order-actions__confirm" data-test="order-delete">
                <ConfirmButton variant="danger" :disabled="busy" :label="t('admin.order.delete.label')" :confirm-label="t('admin.order.delete.confirm')" :message="t('admin.order.delete.message')" @confirm="deleteOrder" />
            </span>
        </div>
        <p v-if="actions.includes('checkPayment')" class="order-actions__hint">{{ t('admin.order.checkPayment.hint') }}</p>

        <BaseModal v-model:open="shipOpen" :title="t('admin.order.ship.label')">
            <form class="order-actions__form" data-test="order-ship-form" @submit.prevent="submitShip">
                <p :id="introId" class="order-actions__hint">{{ t('admin.order.ship.intro') }}</p>
                <FormField v-slot="{ describedBy }" :label="t('admin.order.ship.tracking')" :hint="t('admin.order.ship.trackingHint')" :error="trackingError">
                    <input v-model="tracking" type="text" :aria-describedby="[introId, describedBy].filter(Boolean).join(' ')" :aria-invalid="trackingError ? 'true' : null" name="trackingNumber" :maxlength="LIMITS.trackingNumber" autocomplete="off" spellcheck="false" data-test="order-ship-tracking" @input="trackingError = null" />
                </FormField>
                <div class="actions-row">
                    <BaseButton variant="ghost" @click="shipOpen = false">{{ t('common.cancel') }}</BaseButton>
                    <BaseButton type="submit" :loading="busy" data-test="order-ship-submit">{{ t('admin.order.ship.submit') }}</BaseButton>
                </div>
            </form>
        </BaseModal>
    </section>
</template>

<style scoped>
.order-actions { display: flex; flex-direction: column; gap: var(--space-3); padding: var(--space-4); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.order-actions__heading { font-size: var(--font-size); }
.order-actions__row { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.order-actions__confirm { display: contents; }
.order-actions__hint { color: var(--color-muted); font-size: var(--font-size-md); }
.order-actions__form { display: flex; flex-direction: column; gap: var(--space-4); }

@media (max-width: 40rem) {
    .order-actions__row :deep(.button) { flex: 1 1 100%; }
}
</style>
