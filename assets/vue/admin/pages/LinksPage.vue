<script setup>
import { ArrowDown, ArrowUp, GripVertical, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import ConfirmButton from '../../components/ui/ConfirmButton.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import IconButton from '../../components/ui/IconButton.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import { useToast } from '../../composables/useToast.js';
import LinkEditor from '../components/LinkEditor.vue';
import { useLinks } from '../useContent.js';

const { t } = useI18n();
const toast = useToast();
const { links, load, remove, reorder } = useLinks();
load();

const editing = ref(null);
const editorOpen = ref(false);

function openEditor(link = null) {
    editing.value = link;
    editorOpen.value = true;
}

function saveOrder() {
    toast.attempt(() => reorder(links.value.map((link) => link.id)));
}

function move(index, offset) {
    const list = [...links.value];
    const [link] = list.splice(index, 1);
    list.splice(index + offset, 0, link);
    links.value = list;
    saveOrder();
}

function confirmRemove(link) {
    toast.attempt(() => remove(link.id), t('admin.links.deleted'));
}
</script>

<template>
    <div class="links-page">
        <PageHeader :title="t('admin.links.title')" :subtitle="t('admin.links.subtitle')">
            <BaseButton @click="openEditor()"><Plus size="1rem" aria-hidden="true" />{{ t('admin.links.add') }}</BaseButton>
        </PageHeader>

        <EmptyState v-if="!links.length" :title="t('admin.links.empty')" :hint="t('admin.links.emptyHint')" />

        <VueDraggable
            v-else
            v-model="links"
            tag="ul"
            class="links-page__list"
            handle=".links-page__grip"
            :animation="160"
            ghost-class="links-page__ghost"
            @end="saveOrder"
        >
            <li v-for="(link, index) in links" :key="link.id" class="links-page__item" data-test="link-row">
                <span class="links-page__grip" :title="t('admin.links.move', { title: link.title })"><GripVertical size="1.125rem" aria-hidden="true" /></span>
                <span :class="['links-page__tape', `links-page__tape--${link.tape}`]" aria-hidden="true" />
                <div class="links-page__text">
                    <p class="links-page__title">{{ link.title }}</p>
                    <p class="links-page__description">{{ link.description }}</p>
                    <a class="links-page__url" :href="link.url" target="_blank" rel="noopener">{{ link.url }}</a>
                </div>
                <div class="links-page__actions">
                    <IconButton :icon="ArrowUp" :label="t('admin.moveUp')" :disabled="index === 0" @click="move(index, -1)" />
                    <IconButton :icon="ArrowDown" :label="t('admin.moveDown')" :disabled="index === links.length - 1" @click="move(index, 1)" />
                    <IconButton :icon="Pencil" :label="t('admin.links.edit')" @click="openEditor(link)" />
                    <ConfirmButton :icon="Trash2" :label="t('admin.links.delete')" :message="t('admin.links.deleteMessage', { title: link.title })" @confirm="confirmRemove(link)" />
                </div>
            </li>
        </VueDraggable>

        <LinkEditor v-model:open="editorOpen" :link="editing" />
    </div>
</template>

<style scoped>
.links-page { display: flex; flex-direction: column; gap: var(--space-5); }
.links-page__list { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }

.links-page__item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    border: 0.0625rem solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
}

.links-page__grip { display: inline-flex; color: var(--color-subtle); cursor: grab; touch-action: none; }
.links-page__ghost { opacity: 0.4; }

.links-page__tape { flex-shrink: 0; width: 0.5rem; align-self: stretch; border-radius: 0.125rem; }
.links-page__tape--leaf { background: repeating-linear-gradient(-45deg, #b9d39a 0 0.25rem, #dce9cc 0.25rem 0.5rem); }
.links-page__tape--blossom { background: radial-gradient(circle, #f7e1e4 0 0.1rem, transparent 0.12rem) 0 0 / 0.4rem 0.4rem, #eec3c9; }

.links-page__text { display: flex; flex-direction: column; gap: 0.125rem; min-width: 0; flex: 1; }
.links-page__title { color: var(--color-ink); font-weight: 600; }
.links-page__description { color: var(--color-muted); font-size: var(--font-size-md); }
.links-page__url { overflow: hidden; color: var(--color-subtle); font-size: var(--font-size-sm); text-overflow: ellipsis; white-space: nowrap; }
.links-page__actions { display: flex; flex-shrink: 0; gap: var(--space-1); }

@media (max-width: 40rem) {
    .links-page__item { flex-wrap: wrap; }
    .links-page__actions { width: 100%; justify-content: flex-end; }
}
</style>
