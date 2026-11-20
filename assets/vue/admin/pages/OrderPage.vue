<script setup>
import { ArrowLeft, CircleAlert, Copy, ExternalLink, PackageX } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
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
        <RouterLink class="order-page__back" :to="{ name: 'orders' }"><ArrowLeft size="1rem" aria-hidden="true" />{{ t('admin.order.back') }}</RouterLink>

        <EmptyState v-if="failed && !order" :icon="CircleAlert" :title="t('admin.order.loadFailed')" :hint="t('common.error')" data-test="order-failed">
            <BaseButton variant="secondary" @click="reload">{{ t('admin.retry') }}</BaseButton>
        </EmptyState>

        <p v-else-if="!loaded && !order" class="order-page__hint" role="status">{{ t('admin.orders.loading') }}</p>

        <EmptyState v-else-if="!order" :icon="PackageX" :title="t('admin.order.notFound')" :hint="t('admin.order.notFoundHint')" data-test="order-not-found" />

        <template v-else>
            <PageHeader :title="order.reference">
                <OrderStatusBadge :status="order.status" data-test="order-status" />
                <StatusBadge v-if="order.test" tone="warning" data-test="order-test">{{ t('admin.orders.test') }}</StatusBadge>
            </PageHeader>

            <OrderActions :order="order" />

            <section class="order-page__section" data-test="order-address">
                <header class="order-page__head">
                    <h2 class="order-page__heading">{{ t('admin.order.address.title') }}</h2>
                    <BaseButton variant="secondary" data-test="order-copy-address" @click="copyAddress"><Copy size="1rem" aria-hidden="true" />{{ t('admin.order.address.copy') }}</BaseButton>
                </header>
                <address class="order-page__address"><span v-for="(line, index) in address" :key="index">{{ line }}</span></address>
            </section>

            <section class="order-page__section" data-test="order-contact">
                <h2 class="order-page__heading">{{ t('admin.order.contact.title') }}</h2>
                <dl class="order-page__facts">
                    <div><dt>{{ t('admin.order.contact.email') }}</dt><dd><a :href="`mailto:${order.email}`">{{ order.email }}</a></dd></div>
                    <div v-if="order.phone"><dt>{{ t('admin.order.contact.phone') }}</dt><dd><a :href="`tel:${order.phone.replace(/[^\d+]/g, '')}`">{{ order.phone }}</a></dd></div>
                    <div><dt>{{ t('admin.order.contact.language') }}</dt><dd>{{ language }}</dd></div>
                </dl>
            </section>

            <section class="order-page__section" data-test="order-lines">
                <h2 class="order-page__heading">{{ t('admin.order.lines.title') }}</h2>
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
            </section>

            <section class="order-page__section" data-test="order-history">
                <h2 class="order-page__heading">{{ t('admin.order.dates.title') }}</h2>
                <dl class="order-page__facts">
                    <div v-for="[name, value] in dates" :key="name"><dt>{{ t(`admin.order.dates.${name}`) }}</dt><dd>{{ moment.format(new Date(value)) }}</dd></div>
                    <div v-if="order.transactionCode"><dt>{{ t('admin.order.dates.transaction') }}</dt><dd>{{ order.transactionCode }}</dd></div>
                    <div v-if="order.trackingNumber"><dt>{{ t('admin.order.dates.tracking') }}</dt><dd data-test="order-tracking">{{ order.trackingNumber }}</dd></div>
                </dl>
                <a class="order-page__customer-page" :href="order.statusUrl" target="_blank" rel="noopener" data-test="order-status-link"><ExternalLink size="0.9rem" aria-hidden="true" />{{ t('admin.order.customerPage') }}</a>
            </section>
        </template>
    </div>
</template>

<style scoped>
.order-page { display: flex; flex-direction: column; gap: var(--space-5); }
.order-page__back { display: inline-flex; align-items: center; align-self: flex-start; gap: var(--space-1); color: var(--color-muted); font-size: var(--font-size-md); text-decoration: none; }
.order-page__back:hover { color: var(--color-ink); }
.order-page__hint { color: var(--color-muted); }

.order-page__section { display: flex; flex-direction: column; gap: var(--space-3); padding: var(--space-4); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.order-page__head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3); }
.order-page__heading { font-size: var(--font-size); }

.order-page__address { display: flex; flex-direction: column; font-style: normal; overflow-wrap: anywhere; }

.order-page__facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: var(--space-3); margin: 0; }
.order-page__facts div { display: flex; flex-direction: column; gap: 0.125rem; min-width: 0; }
.order-page__facts dt, .order-page__totals dt { color: var(--color-muted); font-size: var(--font-size-xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.order-page__facts dd, .order-page__totals dd { margin: 0; overflow-wrap: anywhere; }

.order-page__lines { display: flex; flex-direction: column; margin: 0; padding: 0; list-style: none; }
.order-page__line { display: flex; align-items: baseline; gap: var(--space-3); padding: var(--space-2) 0; border-bottom: 0.0625rem solid var(--color-border); }
.order-page__quantity { min-width: 2.25rem; color: var(--color-muted); }
.order-page__item { display: flex; flex: 1; flex-direction: column; min-width: 0; }
.order-page__name { font-weight: 600; overflow-wrap: anywhere; }
.order-page__meta { color: var(--color-muted); font-size: var(--font-size-md); overflow-wrap: anywhere; }
.order-page__amount { font-weight: 600; }

.order-page__totals { display: flex; flex-direction: column; gap: var(--space-1); max-width: 16rem; margin: 0; margin-left: auto; }
.order-page__totals div { display: flex; justify-content: space-between; gap: var(--space-4); }
.order-page__total { padding-top: var(--space-2); border-top: 0.0625rem solid var(--color-border); font-weight: 600; }

.order-page__customer-page { display: inline-flex; align-items: center; align-self: flex-start; gap: var(--space-1); color: var(--color-accent-strong); font-size: var(--font-size-md); }
</style>
