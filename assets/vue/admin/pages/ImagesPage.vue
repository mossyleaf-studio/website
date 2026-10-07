<script setup>
import { ImagePlus } from '@lucide/vue';
import { ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import EmptyState from '../../components/ui/EmptyState.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import { useToast } from '../../composables/useToast.js';
import ArtworkCard from '../components/ArtworkCard.vue';
import { useArtworks } from '../useContent.js';

const { t } = useI18n();
const toast = useToast();
const { artworks, load, upload, reorder } = useArtworks();
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
        <p class="images-page__formats">{{ t('admin.images.formats') }}</p>

        <EmptyState v-if="!artworks.length && !sending" :title="t('admin.images.empty')" :hint="t('admin.images.emptyHint')" />

        <VueDraggable
            v-else
            v-model="artworks"
            class="images-page__grid"
            handle=".artwork-card__grip"
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
                @move="(offset) => move(index, offset)"
            />
        </VueDraggable>
    </div>
</template>

<style scoped>
.images-page { display: flex; flex-direction: column; gap: var(--space-4); }
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
</style>
