<script setup>
import { ArrowLeft, CircleAlert, Copy, ExternalLink, PackageX } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseCard from '../../components/ui/BaseCard.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import { useToast } from '../../composables/useToast.js';
import { dateFormat, intlLocale, LANGUAGES } from '../../i18n/locale.js';
import OrderActions from '../components/OrderActions.vue';
import OrderStatusBadge from '../components/OrderStatusBadge.vue';
import { formatCents } from '../money.js';
import { addressLines, countryName } from '../orders.js';
import { useOrders } from '../useOrders.js';

const props = defineProps({
    id: { type: String, required: true },
});

const { t } = useI18n();
const toast = useToast();
const { orders, loaded, failed, reload } = useOrders();

reload();

const order = computed(() => orders.value.find((candidate) => candidate.id === props.id) ?? null);

const locale = intlLocale();
const moment = dateFormat({ dateStyle: 'medium', timeStyle: 'short' });
const money = (cents) => formatCents(cents, locale);

const address = computed(() => addressLines(order.value, countryName(order.value.country, locale)));
const language = computed(() => LANGUAGES.find((known) => known.value === order.value.locale)?.label ?? order.value.locale);
const dates = computed(() => [
    ['placed', order.value.placedAt],
    ['paid', order.value.paidAt],
    ['shipped', order.value.shippedAt],
    ['cancelled', order.value.cancelledAt],
].filter(([, value]) => value !== null));

async function copyAddress() {
    try {
        await navigator.clipboard.writeText(address.value.join('\n'));
        toast.success(t('admin.order.address.copied'));
    } catch {
        toast.error(t('admin.order.address.copyFailed'));
    }
}
</script>

<template>
    <div class="order-page" data-test="order-page">
        <PageHeader v-if="order" :title="order.reference">
            <template #back><RouterLink :to="{ name: 'orders' }"><ArrowLeft size="0.875rem" aria-hidden="true" />{{ t('admin.order.back') }}</RouterLink></template>
            <OrderStatusBadge :status="order.status" data-test="order-status" />
            <StatusBadge v-if="order.test" tone="warning" data-test="order-test">{{ t('admin.orders.test') }}</StatusBadge>
        </PageHeader>
        <RouterLink v-else class="order-page__back" :to="{ name: 'orders' }"><ArrowLeft size="0.875rem" aria-hidden="true" />{{ t('admin.order.back') }}</RouterLink>

        <EmptyState v-if="failed && !order" :icon="CircleAlert" :title="t('admin.order.loadFailed')" :hint="t('common.error')" data-test="order-failed">
            <BaseButton variant="secondary" @click="reload">{{ t('admin.retry') }}</BaseButton>
        </EmptyState>

        <p v-else-if="!loaded && !order" class="order-page__hint" role="status">{{ t('admin.orders.loading') }}</p>

        <EmptyState v-else-if="!order" :icon="PackageX" :title="t('admin.order.notFound')" :hint="t('admin.order.notFoundHint')" data-test="order-not-found" />

        <div v-else class="order-page__grid">
            <div class="order-page__main">
                <OrderActions :order="order" />

                <BaseCard :title="t('admin.order.lines.title')" data-test="order-lines">
                    <ul class="order-page__lines">
                        <li v-for="line in order.lines" :key="line.sku" class="order-page__line" data-test="order-line">
                            <span class="order-page__quantity tabular">{{ line.quantity }} ×</span>
                            <span class="order-page__item">
                                <span class="order-page__name">{{ line.name }}</span>
                                <span class="order-page__meta">{{ [line.variant, line.sku].filter(Boolean).join(' · ') }}</span>
                                <span class="order-page__meta tabular">{{ t('admin.order.lines.each', { price: money(line.unitPrice) }) }}</span>
                            </span>
                            <span class="order-page__amount tabular">{{ money(line.lineTotal) }}</span>
                        </li>
                    </ul>
                    <dl class="order-page__totals">
                        <div><dt>{{ t('admin.order.lines.goods') }}</dt><dd class="tabular">{{ money(order.goods) }}</dd></div>
                        <div><dt>{{ t('admin.order.lines.shipping') }}</dt><dd class="tabular">{{ money(order.shipping) }}</dd></div>
                        <div class="order-page__total"><dt>{{ t('admin.order.lines.total') }}</dt><dd class="tabular" data-test="order-total">{{ money(order.total) }}</dd></div>
                    </dl>
                </BaseCard>
            </div>

            <aside class="order-page__side">
                <BaseCard :title="t('admin.order.address.title')" data-test="order-address">
                    <template #actions>
                        <BaseButton variant="secondary" data-test="order-copy-address" @click="copyAddress"><Copy size="1rem" aria-hidden="true" />{{ t('admin.order.address.copy') }}</BaseButton>
                    </template>
                    <address class="order-page__address"><span v-for="(line, index) in address" :key="index">{{ line }}</span></address>
                </BaseCard>

                <BaseCard :title="t('admin.order.contact.title')" data-test="order-contact">
                    <dl class="order-page__facts">
                        <div><dt>{{ t('admin.order.contact.email') }}</dt><dd><a :href="`mailto:${order.email}`">{{ order.email }}</a></dd></div>
                        <div v-if="order.phone"><dt>{{ t('admin.order.contact.phone') }}</dt><dd><a :href="`tel:${order.phone.replace(/[^\d+]/g, '')}`">{{ order.phone }}</a></dd></div>
                        <div><dt>{{ t('admin.order.contact.language') }}</dt><dd>{{ language }}</dd></div>
                    </dl>
                </BaseCard>

                <BaseCard :title="t('admin.order.dates.title')" data-test="order-history">
                    <dl class="order-page__facts">
                        <div v-for="[name, value] in dates" :key="name"><dt>{{ t(`admin.order.dates.${name}`) }}</dt><dd>{{ moment.format(new Date(value)) }}</dd></div>
                        <div v-if="order.transactionCode"><dt>{{ t('admin.order.dates.transaction') }}</dt><dd>{{ order.transactionCode }}</dd></div>
                        <div v-if="order.trackingNumber"><dt>{{ t('admin.order.dates.tracking') }}</dt><dd data-test="order-tracking">{{ order.trackingNumber }}</dd></div>
                    </dl>
                    <a class="order-page__customer-page" :href="order.statusUrl" target="_blank" rel="noopener" data-test="order-status-link"><ExternalLink size="0.9rem" aria-hidden="true" />{{ t('admin.order.customerPage') }}</a>
                </BaseCard>
            </aside>
        </div>
    </div>
</template>

<style scoped>
.order-page { display: flex; flex-direction: column; gap: var(--space-5); container-type: inline-size; }
.order-page__back { display: inline-flex; align-items: center; align-self: flex-start; gap: var(--space-1); color: var(--color-muted); font-size: var(--font-size-md); text-decoration: none; }
.order-page__back:hover { color: var(--color-ink); }
.order-page__hint { color: var(--color-muted); }

.order-page__grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(16rem, 1fr); gap: var(--space-5); align-items: start; }
.order-page__main, .order-page__side { display: flex; flex-direction: column; gap: var(--space-5); min-width: 0; }

.order-page__address { display: flex; flex-direction: column; font-style: normal; overflow-wrap: anywhere; }

.order-page__facts { display: flex; flex-direction: column; gap: var(--space-3); margin: 0; }
.order-page__facts div { display: flex; flex-direction: column; gap: 0.125rem; min-width: 0; }
.order-page__facts dt, .order-page__totals dt { color: var(--color-muted); font-size: var(--font-size-sm); }
.order-page__facts dd, .order-page__totals dd { margin: 0; overflow-wrap: anywhere; }

.order-page__lines { display: flex; flex-direction: column; margin: 0; padding: 0; list-style: none; }
.order-page__line { display: flex; align-items: baseline; gap: var(--space-3); padding: var(--space-3) 0; border-bottom: 0.0625rem solid var(--color-border); }
.order-page__line:first-child { border-top: 0.0625rem solid var(--color-border); }
.order-page__quantity { min-width: 2.25rem; color: var(--color-muted); }
.order-page__item { display: flex; flex: 1; flex-direction: column; min-width: 0; }
.order-page__name { font-weight: 600; overflow-wrap: anywhere; }
.order-page__meta { color: var(--color-muted); font-size: var(--font-size-md); overflow-wrap: anywhere; }
.order-page__amount { font-weight: 600; }

.order-page__totals { display: flex; flex-direction: column; gap: var(--space-1); max-width: 18rem; margin: var(--space-4) 0 0 auto; }
.order-page__totals div { display: flex; justify-content: space-between; gap: var(--space-4); }
.order-page__total { padding-top: var(--space-2); border-top: 0.0625rem solid var(--color-border); font-weight: 600; }

.order-page__customer-page { display: inline-flex; align-items: center; gap: var(--space-1); margin-top: var(--space-4); color: var(--color-accent-strong); font-size: var(--font-size-md); }

@container (max-width: 40rem) {
    .order-page__grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
