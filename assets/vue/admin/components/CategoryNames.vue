<script setup>
import { ArrowDown, ArrowUp, Tags } from '@lucide/vue';
import { nextTick, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import IconButton from '../../components/ui/IconButton.vue';
import LocalizedField from '../../components/ui/LocalizedField.vue';
import { ApiError } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { LIMITS } from '../limits.js';
import { bulkProgress } from '../useSelection.js';
import { useCategories } from '../useShop.js';

const open = defineModel('open', { type: Boolean, required: true });

const { t } = useI18n();
const toast = useToast();
const { categories, label, reorder } = useCategories();

const labels = reactive({});
const errors = ref({});
const saving = ref(false);
const moving = ref(false);
const form = ref(null);

watch(open, (isOpen) => {
    if (!isOpen) return;
    errors.value = {};
    categories.value.forEach((category) => { labels[category.id] = { en: category.label, fr: category.labelFr }; });
});

watch(categories, (list) => {
    list.forEach((category) => { labels[category.id] ??= { en: category.label, fr: category.labelFr }; });
}, { immediate: true });

const changed = (category) => labels[category.id].en !== category.label || labels[category.id].fr !== category.labelFr;

async function move(index, offset) {
    const before = categories.value;
    const list = [...before];
    const [category] = list.splice(index, 1);
    list.splice(index + offset, 0, category);
    categories.value = list;
    moving.value = true;
    const saved = await toast.attempt(() => reorder(list.map((item) => item.id)));
    moving.value = false;
    if (!saved) categories.value = before;
    await nextTick();
    const [up, down] = form.value.querySelectorAll(`[data-category="${category.id}"] .category-names__moves button`);
    const [pressed, other] = offset < 0 ? [up, down] : [down, up];
    (pressed.disabled ? other : pressed).focus();
}

async function submit() {
    saving.value = true;
    errors.value = {};
    let failed = false;
    for (const category of categories.value.filter(changed)) {
        try {
            await label(category.id, { label: labels[category.id].en, labelFr: labels[category.id].fr });
        } catch (error) {
            failed = true;
            errors.value = { ...errors.value, [category.id]: error instanceof ApiError && error.violations.length ? error.fieldErrors : { label: error.message } };
        }
    }
    saving.value = false;
    if (failed) return;
    open.value = false;
    toast.success(t('admin.saved'));
}
</script>

<template>
    <BaseModal v-model:open="open" :title="t('admin.categories.title')">
        <form ref="form" class="category-names" data-test="category-names" @submit.prevent="submit">
            <EmptyState v-if="!categories.length" :icon="Tags" :title="t('admin.categories.empty')" :hint="t('admin.categories.emptyDetail')" />
            <template v-else>
                <p class="category-names__hint">{{ t('admin.categories.hint') }}</p>
                <div v-for="(category, index) in categories" :key="category.id" class="category-names__row" :data-category="category.id">
                    <LocalizedField
                        v-model="labels[category.id]"
                        :label="category.name"
                        :hint="t('admin.categories.emptyHint', { name: category.name })"
                        :name="`label-${category.id}`"
                        :max="LIMITS.categoryLabel"
                        own-default
                        :errors="{ en: errors[category.id]?.label, fr: errors[category.id]?.labelFr }"
                        data-test="category-label"
                    />
                    <div class="category-names__moves">
                        <IconButton :icon="ArrowUp" :label="t('admin.categories.moveUp', { name: category.name })" :disabled="moving || bulkProgress !== null || index === 0" data-test="category-move-up" @click="move(index, -1)" />
                        <IconButton :icon="ArrowDown" :label="t('admin.categories.moveDown', { name: category.name })" :disabled="moving || bulkProgress !== null || index === categories.length - 1" data-test="category-move-down" @click="move(index, 1)" />
                    </div>
                </div>
                <div class="actions-row">
                    <BaseButton variant="ghost" @click="open = false">{{ t('common.cancel') }}</BaseButton>
                    <BaseButton type="submit" :loading="saving" data-test="category-names-save">{{ t('admin.categories.save') }}</BaseButton>
                </div>
            </template>
        </form>
    </BaseModal>
</template>

<style scoped>
.category-names { display: flex; flex-direction: column; gap: var(--space-4); }
.category-names__row { display: flex; align-items: center; gap: var(--space-2); }
.category-names__row > :first-child { flex: 1; min-width: 0; }
.category-names__moves { display: flex; flex-direction: column; gap: var(--space-1); }
.category-names__hint { color: var(--color-muted); font-size: var(--font-size-md); }
</style>
