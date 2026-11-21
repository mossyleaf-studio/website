<script setup>
import { Image, ImagePlus } from '@lucide/vue';
import { ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import BaseCard from '../../components/ui/BaseCard.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import { useToast } from '../../composables/useToast.js';
import ArtworkCard from '../components/ArtworkCard.vue';
import BulkBar from '../components/BulkBar.vue';
import SelectCheck from '../components/SelectCheck.vue';
import { useArtworks } from '../useContent.js';
import { useSelection } from '../useSelection.js';

const { t } = useI18n();
const toast = useToast();
const { artworks, load, upload, reorder, remove } = useArtworks();
load();

const input = ref(null);
const sending = ref(0);

async function send(event) {
    const files = [...event.target.files];
    event.target.value = '';
    if (!files.length) return;

    sending.value = files.length;
    let added = 0;
    for (const file of files) {
        try {
            await upload(file);
            added += 1;
        } catch (error) {
            toast.error(t('admin.images.uploadFailed', { file: file.name, error: error.message }));
        }
        sending.value -= 1;
    }
    if (added) toast.success(t('admin.images.uploaded', { count: added }, added));
}

const selection = useSelection(artworks);

const actions = [{
    key: 'delete',
    tone: 'danger',
    label: (count) => t('admin.images.bulk.delete', { count }, count),
    perform: (artwork) => remove(artwork.id),
    confirm: (count) => t('admin.images.bulk.deleteMessage', { count }, count),
    done: 'admin.images.bulk.deleted',
}];

function saveOrder() {
    toast.attempt(() => reorder(artworks.value.map((artwork) => artwork.id)));
}

function move(index, offset) {
    const list = [...artworks.value];
    const [artwork] = list.splice(index, 1);
    list.splice(index + offset, 0, artwork);
    artworks.value = list;
    saveOrder();
}
</script>

<template>
    <div class="images-page">
        <PageHeader :title="t('admin.images.title')" :subtitle="t('admin.images.subtitle')">
            <label :class="['images-page__upload', { 'images-page__upload--busy': sending }]">
                <ImagePlus size="1rem" aria-hidden="true" />
                {{ sending ? t('admin.images.uploading', { count: sending }, sending) : t('admin.images.upload') }}
                <input
                    ref="input"
                    class="visually-hidden"
                    type="file"
                    name="image"
                    accept="image/jpeg,image/png,image/webp,image/avif"
                    multiple
                    :disabled="sending > 0"
                    @change="send"
                />
            </label>
        </PageHeader>
        <BaseCard class="images-page__card">
            <p class="images-page__formats">{{ t('admin.images.formats') }}</p>

            <EmptyState v-if="!artworks.length && !sending" :icon="Image" :title="t('admin.images.empty')" :hint="t('admin.images.emptyHint')" />

            <div v-if="artworks.length" class="images-page__head">
                <SelectCheck :model-value="selection.head()" :label="t('admin.images.bulk.all')" :disabled="selection.locked.value" data-test="select-all" @update:model-value="selection.setAll">{{ t('admin.selection.all') }}</SelectCheck>
            </div>

            <VueDraggable
                v-if="artworks.length || sending"
                v-model="artworks"
                class="images-page__grid"
                handle=".artwork-card__grip"
                :disabled="selection.locked.value"
                :animation="160"
                ghost-class="images-page__ghost"
                @end="saveOrder"
            >
                <ArtworkCard
                    v-for="(artwork, index) in artworks"
                    :key="artwork.id"
                    :artwork="artwork"
                    :first="index === 0"
                    :last="index === artworks.length - 1"
                    :selected="selection.has(artwork.id)"
                    :locked="selection.locked.value"
                    @move="(offset) => move(index, offset)"
                >
                    <template #select>
                        <SelectCheck :model-value="selection.has(artwork.id)" :label="artwork.alt ? t('admin.images.bulk.selectNamed', { alt: artwork.alt }) : t('admin.images.bulk.select', { position: index + 1 })" :disabled="selection.locked.value" data-test="select-row" @update:model-value="(checked) => selection.set(artwork.id, checked)" />
                    </template>
                </ArtworkCard>
            </VueDraggable>
        </BaseCard>

        <BulkBar :selection="selection" :actions="actions" noun="admin.images.bulk.count" />
    </div>
</template>

<style scoped>
.images-page { display: flex; flex-direction: column; gap: var(--space-5); }
.images-page__card { display: flex; flex-direction: column; gap: var(--space-4); }
.images-page__formats { color: var(--color-muted); font-size: var(--font-size-md); }

.images-page__upload {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    min-height: 2.375rem;
    padding: var(--space-2) var(--space-4);
    border-radius: var(--radius);
    background: var(--color-accent);
    color: var(--color-on-accent);
    font-weight: 600;
    font-size: var(--font-size-md);
    cursor: pointer;
}

.images-page__upload:hover { background: var(--color-accent-strong); }
.images-page__upload:has(:focus-visible) { outline: 0.125rem solid var(--color-accent); outline-offset: 0.125rem; }
.images-page__upload--busy { opacity: 0.7; cursor: progress; }

.images-page__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); gap: var(--space-4); }
.images-page__ghost { opacity: 0.4; }
.images-page__head { display: flex; align-items: center; }
</style>
