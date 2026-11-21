<script setup>
import { ArrowDown, ArrowUp, CircleAlert, GripVertical, ImageOff, Package, Pencil, SearchX, Tags } from '@lucide/vue';
import { computed, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseCard from '../../components/ui/BaseCard.vue';
import BaseSelect from '../../components/ui/BaseSelect.vue';
import BaseSwitch from '../../components/ui/BaseSwitch.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import IconButton from '../../components/ui/IconButton.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import { useToast } from '../../composables/useToast.js';
import { intlLocale } from '../../i18n/locale.js';
import CategoryNames from '../components/CategoryNames.vue';
import { formatCents } from '../money.js';
import { completeness, filterProducts, totalStock } from '../products.js';
import { useCategories, useProducts } from '../useShop.js';

const { t } = useI18n();
const toast = useToast();
const { products, load, show, hide, reorder } = useProducts();
const { categories, load: loadCategories } = useCategories();
const loaded = ref(false);
const failed = ref(false);

function fetchProducts() {
    failed.value = false;
    load().then(() => { loaded.value = true; }, () => { failed.value = true; });
}

fetchProducts();
loadCategories();

const animation = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 160;

const query = ref('');
const category = ref('');
const namesOpen = ref(false);

const filtering = computed(() => query.value.trim() !== '' || category.value !== '');
const rows = computed(() => filterProducts(products.value, { query: query.value, category: category.value }));
const categoryOptions = computed(() => [{ value: '', label: t('admin.products.allCategories') }, ...categories.value.map((entry) => ({ value: entry.id, label: entry.name }))]);

const heading = (product) => product.title || product.displayName;

function saveOrder(message = null) {
    toast.attempt(() => reorder(products.value.map((product) => product.id)), message);
}

function move(index, offset) {
    const list = [...products.value];
    const [product] = list.splice(index, 1);
    list.splice(index + offset, 0, product);
    products.value = list;
    saveOrder(t('admin.products.moved', { title: heading(product), position: index + offset + 1 }));
}

function toggle(product, visible) {
    toast.attempt(() => (visible ? show(product.id) : hide(product.id)));
}
</script>

<template>
    <div class="products-page">
        <PageHeader :title="t('admin.products.title')" :subtitle="t('admin.products.subtitle')">
            <BaseButton variant="secondary" data-test="category-names-open" @click="namesOpen = true"><Tags size="1rem" aria-hidden="true" />{{ t('admin.products.categoryNames') }}</BaseButton>
        </PageHeader>

        <BaseCard v-if="failed || loaded || products.length" class="products-page__card">
            <EmptyState v-if="failed && !products.length" :icon="CircleAlert" :title="t('admin.products.loadFailed')" :hint="t('common.error')" data-test="products-failed">
                <BaseButton variant="secondary" @click="fetchProducts">{{ t('admin.retry') }}</BaseButton>
            </EmptyState>

            <EmptyState v-else-if="loaded && !products.length" :icon="Package" :title="t('admin.products.empty')" :hint="t('admin.products.emptyHint')" data-test="products-empty" />

            <template v-else-if="products.length">
                <div class="products-page__filters">
                    <input v-model="query" class="control products-page__search" type="search" name="search" :aria-label="t('admin.products.search')" :placeholder="t('admin.products.searchPlaceholder')" data-test="product-search" />
                    <div class="products-page__category"><BaseSelect v-model="category" :options="categoryOptions" :aria-label="t('admin.products.categoryFilter')" data-test="product-category-filter" /></div>
                </div>
                <p v-if="filtering" class="products-page__note">{{ t('admin.products.filtering') }}</p>

                <EmptyState v-if="!rows.length" :icon="SearchX" :title="t('admin.products.noMatch')" :hint="t('admin.products.noMatchHint')" data-test="products-no-match" />

                <VueDraggable
                    v-else
                    :model-value="rows"
                    tag="ul"
                    class="products-page__list"
                    handle=".products-page__grip"
                    :disabled="filtering"
                    :animation="animation"
                    ghost-class="products-page__ghost"
                    @update:model-value="(list) => { products = list; }"
                    @end="saveOrder()"
                >
                    <li v-for="(product, index) in rows" :key="product.id" class="products-page__item" data-test="product-row" :data-reference="product.reference">
                        <span :class="['products-page__grip', { 'products-page__grip--off': filtering }]" :title="t('admin.products.move', { title: heading(product) })"><GripVertical size="1.125rem" aria-hidden="true" /></span>
                        <div class="products-page__thumb">
                            <img v-if="product.photos.length" :src="product.photos[0].thumbUrl" alt="" :width="product.photos[0].thumbWidth" :height="product.photos[0].thumbHeight" loading="lazy" />
                            <ImageOff v-else size="1.25rem" aria-hidden="true" />
                        </div>
                        <div class="products-page__text">
                            <RouterLink class="products-page__name" :to="{ name: 'product', params: { id: product.id } }">{{ heading(product) }}</RouterLink>
                            <p class="products-page__meta">{{ product.reference }} · {{ product.category.name }}</p>
                            <div class="products-page__chips">
                                <StatusBadge v-if="product.retired" data-test="product-retired">{{ t('admin.products.retired') }}</StatusBadge>
                                <StatusBadge v-for="gap in completeness(product)" :key="gap" tone="warning" data-test="product-chip">{{ t(`admin.products.chips.${gap}`) }}</StatusBadge>
                            </div>
                        </div>
                        <p class="products-page__price tabular">{{ formatCents(product.price, intlLocale()) }}</p>
                        <p class="products-page__stock tabular">{{ totalStock(product) > 0 ? t('admin.products.stock', { count: totalStock(product) }) : t('admin.products.soldOut') }}</p>
                        <BaseSwitch
                            :model-value="product.visible"
                            :label="t('admin.products.visible', { title: heading(product) })"
                            :disabled="product.retired && !product.visible"
                            data-test="product-visibility"
                            @update:model-value="(visible) => toggle(product, visible)"
                        />
                        <div class="products-page__actions">
                            <IconButton :icon="ArrowUp" :label="t('admin.products.moveUp', { title: heading(product) })" :disabled="filtering || index === 0" @click="move(index, -1)" />
                            <IconButton :icon="ArrowDown" :label="t('admin.products.moveDown', { title: heading(product) })" :disabled="filtering || index === rows.length - 1" @click="move(index, 1)" />
                            <RouterLink class="products-page__edit" :to="{ name: 'product', params: { id: product.id } }" tabindex="-1" aria-hidden="true" :title="t('admin.products.edit', { title: heading(product) })" data-test="product-edit">
                                <Pencil size="1.125rem" aria-hidden="true" />
                            </RouterLink>
                        </div>
                    </li>
                </VueDraggable>
            </template>
        </BaseCard>

        <CategoryNames v-model:open="namesOpen" />
    </div>
</template>

<style scoped>
.products-page { display: flex; flex-direction: column; gap: var(--space-5); }
.products-page__card { display: flex; flex-direction: column; gap: var(--space-4); }
.products-page__filters { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.products-page__search { flex: 0 1 20rem; }
.products-page__category { flex: 0 1 16rem; min-width: 10rem; }
.products-page__note { color: var(--color-muted); font-size: var(--font-size-md); }
.products-page__list { display: flex; flex-direction: column; margin: 0; padding: 0; list-style: none; border-top: 0.0625rem solid var(--color-border); }

.products-page__item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-2);
    border-bottom: 0.0625rem solid var(--color-border);
    background: var(--color-surface);
    transition: background var(--transition);
}

.products-page__item:hover { background: var(--color-hover); }

.products-page__grip { display: inline-flex; color: var(--color-muted); cursor: grab; touch-action: none; }
.products-page__grip--off { cursor: not-allowed; opacity: 0.4; }
.products-page__ghost { opacity: 0.4; }

.products-page__thumb {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 3.5rem;
    height: 3.5rem;
    overflow: hidden;
    border: 0.0625rem solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-bg);
    color: var(--color-subtle);
}

.products-page__thumb img { width: 100%; height: 100%; object-fit: cover; }

.products-page__text { display: flex; flex: 1; flex-direction: column; gap: 0.125rem; min-width: 0; }
.products-page__name { overflow: hidden; color: var(--color-ink); font-weight: 600; text-decoration: none; text-overflow: ellipsis; white-space: nowrap; }
.products-page__name:hover { text-decoration: underline; }
.products-page__meta { color: var(--color-muted); font-size: var(--font-size-sm); }
.products-page__chips { display: flex; flex-wrap: wrap; gap: var(--space-1); }
.products-page__chips:empty { display: none; }
.products-page__price { min-width: 4.5rem; font-weight: 600; text-align: right; }
.products-page__stock { min-width: 6rem; color: var(--color-muted); font-size: var(--font-size-md); }
.products-page__actions { display: flex; flex-shrink: 0; align-items: center; gap: var(--space-1); }

.products-page__edit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: 0.0625rem solid transparent;
    border-radius: var(--radius);
    color: var(--color-muted);
}

.products-page__edit:hover { border-color: var(--color-border); background: var(--color-bg); color: var(--color-ink); }

@media (max-width: 40rem) {
    .products-page__item { flex-wrap: wrap; }
    .products-page__text { flex-basis: calc(100% - 7rem); }
    .products-page__price, .products-page__stock { min-width: 0; text-align: left; }
    .products-page__actions { margin-left: auto; }
}
</style>
