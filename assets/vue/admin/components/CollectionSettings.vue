<script setup>
import { ArrowDown, ArrowUp, Layers } from '@lucide/vue';
import { nextTick, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import BaseSwitch from '../../components/ui/BaseSwitch.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import IconButton from '../../components/ui/IconButton.vue';
import LocalizedField from '../../components/ui/LocalizedField.vue';
import { ApiError } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { LIMITS } from '../limits.js';
import { bulkProgress } from '../useSelection.js';
import { useCollections } from '../useShop.js';

const open = defineModel('open', { type: Boolean, required: true });

const { t } = useI18n();
const toast = useToast();
const { collections, label, show, hide, reorder } = useCollections();

const labels = reactive({});
const errors = ref({});
const saving = ref(false);
const moving = ref(false);
const toggling = ref(0);
const form = ref(null);

async function toggle(collection, shown) {
    toggling.value += 1;
    await toast.attempt(() => (shown ? show(collection.id) : hide(collection.id)));
    toggling.value -= 1;
}

watch(open, (isOpen) => {
    if (!isOpen) return;
    errors.value = {};
    collections.value.forEach((collection) => { labels[collection.id] = { en: collection.label, fr: collection.labelFr }; });
});

watch(collections, (list) => {
    list.forEach((collection) => { labels[collection.id] ??= { en: collection.label, fr: collection.labelFr }; });
}, { immediate: true });

const changed = (collection) => labels[collection.id].en !== collection.label || labels[collection.id].fr !== collection.labelFr;

async function move(index, offset) {
    const before = collections.value;
    const list = [...before];
    const [collection] = list.splice(index, 1);
    list.splice(index + offset, 0, collection);
    collections.value = list;
    moving.value = true;
    const saved = await toast.attempt(() => reorder(list.map((item) => item.id)));
    moving.value = false;
    if (!saved) collections.value = before;
    await nextTick();
    const [up, down] = form.value.querySelectorAll(`[data-collection="${collection.id}"] .collection-names__moves button`);
    const [pressed, other] = offset < 0 ? [up, down] : [down, up];
    (pressed.disabled ? other : pressed).focus();
}

async function submit() {
    saving.value = true;
    errors.value = {};
    let failed = false;
    for (const collection of collections.value.filter(changed)) {
        try {
            await label(collection.id, { label: labels[collection.id].en, labelFr: labels[collection.id].fr });
        } catch (error) {
            failed = true;
            errors.value = { ...errors.value, [collection.id]: error instanceof ApiError && error.violations.length ? error.fieldErrors : { label: error.message } };
        }
    }
    saving.value = false;
    if (failed) return;
    open.value = false;
    toast.success(t('admin.saved'));
}
</script>

<template>
    <BaseModal v-model:open="open" :title="t('admin.collections.title')">
        <form ref="form" class="collection-names" data-test="collection-settings" @submit.prevent="submit">
            <EmptyState v-if="!collections.length" :icon="Layers" :title="t('admin.collections.empty')" :hint="t('admin.collections.emptyDetail')" />
            <template v-else>
                <p class="collection-names__hint">{{ t('admin.collections.hint') }}</p>
                <div v-for="(collection, index) in collections" :key="collection.id" class="collection-names__row" :data-collection="collection.id" data-test="collection-row">
                    <LocalizedField
                        v-model="labels[collection.id]"
                        :label="collection.name"
                        :hint="t('admin.collections.emptyHint', { name: collection.name })"
                        :name="`label-${collection.id}`"
                        :max="LIMITS.collectionLabel"
                        own-default
                        :errors="{ en: errors[collection.id]?.label, fr: errors[collection.id]?.labelFr }"
                        data-test="collection-label"
                    />
                    <div class="collection-names__side">
                        <BaseSwitch :model-value="collection.shown" :label="t('admin.collections.show', { name: collection.name })" :disabled="moving || bulkProgress !== null" data-test="collection-shown" @update:model-value="(shown) => toggle(collection, shown)" />
                        <span class="collection-names__state">{{ collection.shown ? t('admin.collections.shown') : t('admin.collections.hidden') }}</span>
                        <span class="collection-names__count tabular">{{ t('admin.collections.onSale', { count: collection.onSale }, collection.onSale) }}</span>
                    </div>
                    <div class="collection-names__moves">
                        <IconButton :icon="ArrowUp" :label="t('admin.collections.moveUp', { name: collection.name })" :disabled="moving || toggling > 0 || bulkProgress !== null || index === 0" data-test="collection-move-up" @click="move(index, -1)" />
                        <IconButton :icon="ArrowDown" :label="t('admin.collections.moveDown', { name: collection.name })" :disabled="moving || toggling > 0 || bulkProgress !== null || index === collections.length - 1" data-test="collection-move-down" @click="move(index, 1)" />
                    </div>
                </div>
                <div class="actions-row">
                    <BaseButton variant="ghost" @click="open = false">{{ t('common.cancel') }}</BaseButton>
                    <BaseButton type="submit" :loading="saving" data-test="collections-save">{{ t('admin.collections.save') }}</BaseButton>
                </div>
            </template>
        </form>
    </BaseModal>
</template>

<style scoped>
.collection-names { display: flex; flex-direction: column; gap: var(--space-4); }
.collection-names__row { display: flex; align-items: center; gap: var(--space-2); }
.collection-names__row > :first-child { flex: 1; min-width: 0; }
.collection-names__side { display: flex; flex-direction: column; align-items: center; gap: var(--space-1); flex: none; width: 9rem; text-align: center; }
.collection-names__state, .collection-names__count { color: var(--color-muted); font-size: var(--font-size-md); }
.collection-names__moves { display: flex; flex-direction: column; gap: var(--space-1); }
.collection-names__hint { color: var(--color-muted); font-size: var(--font-size-md); }
@media (max-width: 40rem) {
    .collection-names__row { flex-wrap: wrap; }
    .collection-names__row > :first-child { flex-basis: 100%; }
    .collection-names__side { flex-direction: row; flex-wrap: wrap; width: auto; flex: 1; text-align: start; gap: var(--space-2); }
    .collection-names__moves { flex-direction: row; }
}
</style>
