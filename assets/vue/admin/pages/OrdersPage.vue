<script setup>
import { CircleAlert, SearchX, ShoppingBag } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseCard from '../../components/ui/BaseCard.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import { dateFormat, intlLocale } from '../../i18n/locale.js';
import BulkBar from '../components/BulkBar.vue';
import OrderStatusBadge from '../components/OrderStatusBadge.vue';
import SelectCheck from '../components/SelectCheck.vue';
import { formatCents } from '../money.js';
import { countryName, ordersOfTab, searchOrders, TABS, tabCounts } from '../orders.js';
import { useOrders } from '../useOrders.js';
import { useSelection } from '../useSelection.js';

const { t } = useI18n();
const { orders, loaded, failed, reload, ship, remove } = useOrders();

reload();

const tab = ref('toShip');
const query = ref('');

const matches = computed(() => searchOrders(orders.value, query.value));
const counts = computed(() => tabCounts(matches.value));
const rows = computed(() => ordersOfTab(matches.value, tab.value));
const locale = intlLocale();
const day = dateFormat({ dateStyle: 'medium' });

const selection = useSelection(rows);

function deleteSkip(order) {
    if (!order.test) return 'notTest';
    return order.status === 'pending' ? 'checkoutOpen' : null;
}

const actions = [
    {
        key: 'ship',
        label: (count) => t('admin.orders.bulk.ship', { count }, count),
        skip: (order) => (order.status === 'paid' ? null : 'notPaid'),
        perform: (order) => ship(order.id, ''),
        confirm: (count) => t('admin.orders.bulk.shipMessage', { count }, count),
        done: 'admin.orders.bulk.shipped',
    },
    {
        key: 'delete',
        tone: 'danger',
        label: (count) => t('admin.orders.bulk.delete', { count }, count),
        skip: deleteSkip,
        perform: (order) => remove(order.id),
        confirm: (count) => t('admin.orders.bulk.deleteMessage', { count }, count),
        done: 'admin.orders.bulk.deleted',
    },
];
</script>

<template>
    <div class="orders-page">
        <PageHeader :title="t('admin.orders.title')" :subtitle="t('admin.orders.subtitle')" />

        <BaseCard class="orders-page__card">
            <EmptyState v-if="failed && !orders.length" :icon="CircleAlert" :title="t('admin.orders.loadFailed')" :hint="t('common.error')" data-test="orders-failed">
                <BaseButton variant="secondary" @click="reload">{{ t('admin.retry') }}</BaseButton>
            </EmptyState>

            <p v-else-if="!loaded && !orders.length" class="orders-page__note" role="status" data-test="orders-loading">{{ t('admin.orders.loading') }}</p>

            <EmptyState v-else-if="!orders.length" :icon="ShoppingBag" :title="t('admin.orders.empty')" :hint="t('admin.orders.emptyHint')" data-test="orders-empty" />

            <template v-else>
                <div class="orders-page__toolbar">
                    <div class="orders-page__tabs" role="group" :aria-label="t('admin.orders.tabsLabel')">
                        <button v-for="name in TABS" :key="name" type="button" :class="['orders-page__tab', { 'orders-page__tab--active': tab === name }]" :aria-pressed="tab === name" :data-test="`order-tab-${name}`" @click="tab = name">
                            {{ t(`admin.orders.tabs.${name}`) }}<span class="orders-page__count tabular" :data-test="`order-count-${name}`">{{ counts[name] }}</span>
                        </button>
                    </div>
                    <input v-model="query" class="control orders-page__search" type="search" name="search" :aria-label="t('admin.orders.search')" :placeholder="t('admin.orders.searchPlaceholder')" data-test="order-search" />
                </div>

                <EmptyState v-if="!matches.length" :icon="SearchX" :title="t('admin.orders.noMatch')" :hint="t('admin.orders.noMatchHint')" data-test="orders-no-match" />

                <EmptyState v-else-if="!rows.length && query.trim()" :icon="SearchX" :title="t('admin.orders.noMatchInTab')" :hint="t('admin.orders.noMatchInTabHint')" data-test="orders-tab-no-match" />

                <EmptyState v-else-if="!rows.length" :icon="ShoppingBag" :title="t(`admin.orders.emptyTab.${tab}`)" :hint="t('admin.orders.emptyTabHint')" data-test="orders-tab-empty" />

                <template v-else>
                    <div class="orders-page__head">
                        <SelectCheck :model-value="selection.head()" :label="t('admin.orders.bulk.all')" :disabled="selection.locked.value" data-test="select-all" @update:model-value="selection.setAll">{{ t('admin.selection.all') }}</SelectCheck>
                    </div>
                    <ul class="orders-page__list">
                        <li class="orders-page__row orders-page__row--head" aria-hidden="true">
                            <span class="orders-page__check" />
                            <span class="orders-page__ref">{{ t('admin.orders.columns.reference') }}</span>
                            <span class="orders-page__date">{{ t('admin.orders.columns.date') }}</span>
                            <span class="orders-page__customer">{{ t('admin.orders.columns.customer') }}</span>
                            <span class="orders-page__country">{{ t('admin.orders.columns.country') }}</span>
                            <span class="orders-page__items">{{ t('admin.orders.columns.items') }}</span>
                            <span class="orders-page__total">{{ t('admin.orders.columns.total') }}</span>
                            <span class="orders-page__status">{{ t('admin.orders.columns.status') }}</span>
                        </li>
                        <li v-for="order in rows" :key="order.id" :class="['orders-page__row', { 'orders-page__row--selected': selection.has(order.id) }]" data-test="order-row" :data-reference="order.reference">
                            <span class="orders-page__check"><SelectCheck :model-value="selection.has(order.id)" :label="t('admin.orders.bulk.select', { reference: order.reference })" :disabled="selection.locked.value" data-test="select-row" @update:model-value="(checked) => selection.set(order.id, checked)" /></span>
                            <span class="orders-page__ref">
                                <RouterLink class="orders-page__link" :to="{ name: 'order', params: { id: order.id } }" :aria-label="t('admin.orders.open', { reference: order.reference })">{{ order.reference }}</RouterLink>
                                <StatusBadge v-if="order.test" tone="warning" data-test="order-test">{{ t('admin.orders.test') }}</StatusBadge>
                            </span>
                            <span class="orders-page__date">{{ day.format(new Date(order.placedAt)) }}</span>
                            <span class="orders-page__customer">{{ order.name }}</span>
                            <span class="orders-page__country">{{ countryName(order.country, locale) }}</span>
                            <span class="orders-page__items">{{ t('admin.orders.items', { count: order.units }, order.units) }}</span>
                            <span class="orders-page__total tabular">{{ formatCents(order.total, locale) }}</span>
                            <span class="orders-page__status"><OrderStatusBadge :status="order.status" /></span>
                        </li>
                    </ul>
                </template>
            </template>
        </BaseCard>

        <BulkBar :selection="selection" :actions="actions" noun="admin.orders.bulk.count" />
    </div>
</template>

<style scoped>
.orders-page { display: flex; flex-direction: column; gap: var(--space-5); }
.orders-page__card { display: flex; flex-direction: column; gap: var(--space-4); container-type: inline-size; }
.orders-page__note { color: var(--color-muted); }

.orders-page__toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3); }
.orders-page__search { flex: 0 1 20rem; }
.orders-page__tabs { display: flex; flex-wrap: wrap; gap: var(--space-2); }

.orders-page__tab {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    min-height: 2.5rem;
    padding: var(--space-2) var(--space-4);
    border: 0.0625rem solid var(--color-border-strong);
    border-radius: var(--radius-pill);
    background: var(--color-surface);
    color: var(--color-muted);
    font-weight: 600;
    font-size: var(--font-size-md);
    cursor: pointer;
}

.orders-page__tab:hover { color: var(--color-ink); border-color: var(--color-ink); }
.orders-page__tab--active { border-color: var(--color-accent); background: var(--color-accent-soft); color: var(--color-accent-strong); }
.orders-page__count { font-weight: 400; }

.orders-page__list { display: flex; flex-direction: column; margin: 0; padding: 0; list-style: none; }

.orders-page__row {
    position: relative;
    display: grid;
    grid-template-columns: auto repeat(3, minmax(0, 1fr));
    grid-template-areas:
        "check ref ref status"
        "customer customer customer total"
        "date date country items";
    align-items: center;
    gap: var(--space-1) var(--space-3);
    padding: var(--space-3) var(--space-2);
    border-bottom: 0.0625rem solid var(--color-border);
    transition: background var(--transition);
}

.orders-page__row:not(.orders-page__row--head):hover { background: var(--color-hover); }
.orders-page__row--selected,
.orders-page__row--selected:not(.orders-page__row--head):hover { background: var(--color-accent-soft); }
.orders-page__head { display: flex; align-items: center; padding: 0 var(--space-2); border-bottom: 0.0625rem solid var(--color-border); }
.orders-page__check { grid-area: check; display: flex; }
.orders-page__row--head { display: none; }

.orders-page__ref { grid-area: ref; display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); min-width: 0; }
.orders-page__date { grid-area: date; white-space: nowrap; }
.orders-page__customer { grid-area: customer; overflow: hidden; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.orders-page__country { grid-area: country; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.orders-page__items { grid-area: items; }
.orders-page__total { grid-area: total; font-weight: 600; text-align: right; }
.orders-page__status { grid-area: status; justify-self: end; }
.orders-page__date, .orders-page__country, .orders-page__items { color: var(--color-muted); font-size: var(--font-size-md); }

.orders-page__link { color: var(--color-ink); font-weight: 600; text-decoration: none; white-space: nowrap; }
.orders-page__link::after { content: ""; position: absolute; inset: 0; border-radius: var(--radius); }
.orders-page__link:focus-visible { outline: none; }
.orders-page__link:focus-visible::after { outline: 0.125rem solid var(--color-accent); outline-offset: 0.125rem; }
.orders-page__link:hover { text-decoration: underline; }

@container (min-width: 48rem) {
    .orders-page__row {
        grid-template-columns: 2.5rem 8.75rem 6.5rem minmax(0, 1.5fr) minmax(0, 1fr) 4.5rem 5rem 7rem;
        grid-template-areas: "check ref date customer country items total status";
        gap: var(--space-2);
        padding-block: 0.4375rem;
    }

    .orders-page__row--head { display: grid; padding-block: var(--space-2); border-bottom-color: var(--color-border-strong); }
    .orders-page__row--head > span { color: var(--color-muted); font-size: var(--font-size-sm); font-weight: 500; white-space: nowrap; }
    .orders-page__customer { font-weight: 400; }
}
</style>
