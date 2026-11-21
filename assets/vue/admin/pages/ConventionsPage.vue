<script setup>
import { CalendarDays, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseCard from '../../components/ui/BaseCard.vue';
import ConfirmButton from '../../components/ui/ConfirmButton.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import IconButton from '../../components/ui/IconButton.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import { useToast } from '../../composables/useToast.js';
import { dateFormat } from '../../i18n/locale.js';
import ConventionEditor from '../components/ConventionEditor.vue';
import { splitConventions } from '../conventions.js';
import { useConventions } from '../useShop.js';

const { t } = useI18n();
const toast = useToast();
const { conventions, load, remove } = useConventions();
load();

const groups = computed(() => splitConventions(conventions.value));
const editing = ref(null);
const editorOpen = ref(false);

function openEditor(convention = null) {
    editing.value = convention;
    editorOpen.value = true;
}

const day = (value) => new Date(`${value}T00:00:00`);

function days(convention) {
    return dateFormat({ dateStyle: 'medium' }).formatRange(day(convention.startsOn), day(convention.endsOn));
}

function confirmRemove(convention) {
    toast.attempt(async () => {
        await remove(convention.id);
        await load();
    }, t('admin.conventions.deleted'));
}
</script>

<template>
    <div class="conventions-page">
        <PageHeader :title="t('admin.conventions.title')" :subtitle="t('admin.conventions.subtitle')">
            <BaseButton data-test="convention-add" @click="openEditor()"><Plus size="1rem" aria-hidden="true" />{{ t('admin.conventions.add') }}</BaseButton>
        </PageHeader>

        <EmptyState v-if="!conventions.length" :icon="CalendarDays" :title="t('admin.conventions.empty')" :hint="t('admin.conventions.emptyHint')" />

        <template v-for="group in ['upcoming', 'past']" :key="group">
            <BaseCard v-if="groups[group].length" :title="t(`admin.conventions.${group}`)" :data-test="`conventions-${group}`">
                <ul class="conventions-page__list">
                    <li v-for="convention in groups[group]" :key="convention.id" :class="['conventions-page__item', { 'conventions-page__item--past': group === 'past' }]" data-test="convention-row">
                        <div class="conventions-page__text">
                            <p class="conventions-page__name">{{ convention.name }}</p>
                            <p class="conventions-page__meta">{{ convention.place }} · {{ days(convention) }}</p>
                        </div>
                        <div class="conventions-page__actions">
                            <IconButton :icon="Pencil" :label="t('admin.conventions.edit', { name: convention.name })" @click="openEditor(convention)" />
                            <ConfirmButton :icon="Trash2" :label="t('admin.conventions.delete', { name: convention.name })" :message="t('admin.conventions.deleteMessage', { name: convention.name })" @confirm="confirmRemove(convention)" />
                        </div>
                    </li>
                </ul>
            </BaseCard>
        </template>

        <ConventionEditor v-model:open="editorOpen" :convention="editing" />
    </div>
</template>

<style scoped>
.conventions-page { display: flex; flex-direction: column; gap: var(--space-5); }
.conventions-page__list { display: flex; flex-direction: column; margin: 0; padding: 0; list-style: none; border-top: 0.0625rem solid var(--color-border); }

.conventions-page__item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-2);
    border-bottom: 0.0625rem solid var(--color-border);
    transition: background var(--transition);
}

.conventions-page__item:hover { background: var(--color-hover); }
.conventions-page__item--past .conventions-page__name { color: var(--color-muted); }
.conventions-page__text { display: flex; flex: 1; flex-direction: column; gap: 0.125rem; min-width: 0; }
.conventions-page__name { color: var(--color-ink); font-weight: 600; }
.conventions-page__meta { color: var(--color-muted); font-size: var(--font-size-md); }
.conventions-page__actions { display: flex; flex-shrink: 0; gap: var(--space-1); }
</style>
