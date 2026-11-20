<script setup>
import { ArrowLeft } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { onBeforeRouteLeave } from 'vue-router';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseSwitch from '../../components/ui/BaseSwitch.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import FormField from '../../components/ui/FormField.vue';
import LocalizedField from '../../components/ui/LocalizedField.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import { ApiError } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { intlLocale } from '../../i18n/locale.js';
import ProductPhotos from '../components/ProductPhotos.vue';
import { LIMITS } from '../limits.js';
import { formatCents } from '../money.js';
import { useProducts } from '../useShop.js';

const props = defineProps({
    id: { type: String, required: true },
});

const { t } = useI18n();
const toast = useToast();
const { products, load, describe, show, hide } = useProducts();

const loaded = ref(false);
const failed = ref(false);

function fetchProducts() {
    failed.value = false;
    load().then(() => { loaded.value = true; }, () => { failed.value = true; });
}

fetchProducts();

const product = computed(() => products.value.find((candidate) => candidate.id === props.id) ?? null);

const form = reactive({ title: { en: '', fr: '' }, description: { en: '', fr: '' }, slug: '' });
const errors = ref({});
const saving = ref(false);

function snapshot(current) {
    return { title: { en: current.title, fr: current.titleFr }, description: { en: current.description, fr: current.descriptionFr }, slug: current.slug };
}

function differs(current) {
    return form.title.en !== current.title || form.title.fr !== current.titleFr
        || form.description.en !== current.description || form.description.fr !== current.descriptionFr
        || form.slug !== current.slug;
}

watch(
    product,
    (current, previous) => {
        if (current && (previous?.id !== current.id || !differs(previous))) Object.assign(form, snapshot(current));
    },
    { immediate: true },
);

const changed = computed(() => product.value !== null && differs(product.value));

onBeforeRouteLeave(() => !changed.value || window.confirm(t('admin.product.leave')));

const heading = computed(() => product.value.title || product.value.displayName);

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        await describe(props.id, { title: form.title.en, titleFr: form.title.fr, description: form.description.en, descriptionFr: form.description.fr, slug: form.slug });
        Object.assign(form, snapshot(product.value));
        toast.success(t('admin.saved'));
    } catch (error) {
        if (error instanceof ApiError && error.violations.length) errors.value = error.fieldErrors;
        else if (error instanceof ApiError && error.status === 422) errors.value = { slug: error.message };
        else toast.error(error.message);
    } finally {
        saving.value = false;
    }
}

function toggle(visible) {
    toast.attempt(() => (visible ? show(props.id) : hide(props.id)));
}
</script>

<template>
    <div class="product-page" data-test="product-editor">
        <RouterLink class="product-page__back" :to="{ name: 'products' }"><ArrowLeft size="1rem" aria-hidden="true" />{{ t('admin.product.back') }}</RouterLink>

        <EmptyState v-if="failed && !product" :title="t('admin.product.loadFailed')" :hint="t('common.error')" data-test="product-failed">
            <BaseButton variant="secondary" @click="fetchProducts">{{ t('admin.retry') }}</BaseButton>
        </EmptyState>

        <EmptyState v-else-if="loaded && !product" :title="t('admin.product.notFound')" :hint="t('admin.product.notFoundHint')" data-test="product-not-found" />

        <template v-else-if="product">
            <PageHeader :title="heading" :subtitle="product.reference">
                <StatusBadge v-if="product.retired">{{ t('admin.products.retired') }}</StatusBadge>
            </PageHeader>

            <section class="product-page__section" data-test="product-mossytrunk">
                <h2 class="product-page__heading">{{ t('admin.product.mossytrunk.title') }}</h2>
                <p class="product-page__hint">{{ t('admin.product.mossytrunk.hint') }}</p>
                <dl class="product-page__facts">
                    <div><dt>{{ t('admin.product.mossytrunk.reference') }}</dt><dd>{{ product.reference }}</dd></div>
                    <div><dt>{{ t('admin.product.mossytrunk.name') }}</dt><dd>{{ product.displayName }}</dd></div>
                    <div><dt>{{ t('admin.product.mossytrunk.category') }}</dt><dd>{{ product.category.name }}</dd></div>
                    <div><dt>{{ t('admin.product.mossytrunk.price') }}</dt><dd class="tabular" data-test="product-price">{{ formatCents(product.price, intlLocale()) }}</dd></div>
                </dl>
                <table class="product-page__variants">
                    <caption class="visually-hidden">{{ t('admin.product.mossytrunk.variants') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ t('admin.product.mossytrunk.sku') }}</th>
                            <th scope="col">{{ t('admin.product.mossytrunk.variant') }}</th>
                            <th scope="col" class="product-page__number">{{ t('admin.product.mossytrunk.stock') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="variant in product.variants" :key="variant.sku" data-test="product-variant">
                            <td>{{ variant.sku }}</td>
                            <td>{{ variant.name ?? t('admin.product.mossytrunk.noVariant') }}</td>
                            <td class="product-page__number tabular">{{ variant.stock }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <ProductPhotos class="product-page__section" :product="product" />

            <form class="product-page__section" data-test="product-form" @submit.prevent="submit">
                <h2 class="product-page__heading">{{ t('admin.product.texts.title') }}</h2>
                <LocalizedField
                    v-model="form.title"
                    :label="t('admin.product.texts.name')"
                    :hint="t('admin.product.texts.nameHint', { name: product.displayName })"
                    name="title"
                    :max="LIMITS.productTitle"
                    own-default
                    :errors="{ en: errors.title, fr: errors.titleFr }"
                />
                <LocalizedField
                    v-model="form.description"
                    :label="t('admin.product.texts.description')"
                    name="description"
                    :max="LIMITS.productDescription"
                    :rows="6"
                    :errors="{ en: errors.description, fr: errors.descriptionFr }"
                />
                <FormField :label="t('admin.product.texts.slug')" :hint="t('admin.product.texts.slugHint')" :error="errors.slug">
                    <input v-model="form.slug" type="text" name="slug" :maxlength="LIMITS.productSlug" spellcheck="false" autocapitalize="none" data-test="product-slug" @input="errors.slug = undefined" />
                </FormField>
                <div class="actions-row">
                    <BaseButton type="submit" :loading="saving" :disabled="!changed" data-test="product-save">{{ t('admin.product.texts.save') }}</BaseButton>
                </div>
            </form>

            <section class="product-page__section">
                <h2 class="product-page__heading">{{ t('admin.product.visibility.title') }}</h2>
                <div class="product-page__visibility">
                    <BaseSwitch
                        :model-value="product.visible"
                        :label="t('admin.product.visibility.label')"
                        :disabled="product.retired && !product.visible"
                        data-test="product-visibility"
                        @update:model-value="toggle"
                    />
                    <p>{{ product.visible ? t('admin.product.visibility.on') : t('admin.product.visibility.off') }}</p>
                </div>
                <p v-if="product.retired" class="product-page__hint">{{ t('admin.products.retiredHint') }}</p>
            </section>
        </template>
    </div>
</template>

<style scoped>
.product-page { display: flex; flex-direction: column; gap: var(--space-5); }
.product-page__back { display: inline-flex; align-items: center; align-self: flex-start; gap: var(--space-1); color: var(--color-muted); font-size: var(--font-size-md); text-decoration: none; }
.product-page__back:hover { color: var(--color-ink); }

.product-page__section { display: flex; flex-direction: column; gap: var(--space-3); padding: var(--space-4); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.product-page__heading { font-size: var(--font-size); }
.product-page__hint { color: var(--color-muted); font-size: var(--font-size-md); }

.product-page__facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: var(--space-3); margin: 0; }
.product-page__facts div { display: flex; flex-direction: column; gap: 0.125rem; min-width: 0; }
.product-page__facts dt { color: var(--color-muted); font-size: var(--font-size-xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.product-page__facts dd { margin: 0; overflow-wrap: anywhere; }

.product-page__variants { width: 100%; border-collapse: collapse; font-size: var(--font-size-md); }
.product-page__variants :is(th, td) { padding: var(--space-2) var(--space-2); border-bottom: 0.0625rem solid var(--color-border); text-align: left; }
.product-page__variants th { color: var(--color-muted); font-weight: 600; }
.product-page__variants .product-page__number { text-align: right; }

.product-page__visibility { display: flex; align-items: center; gap: var(--space-3); }
</style>
