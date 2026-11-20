<script setup>
import { ArrowDown, ArrowUp, GripVertical, Image, ImagePlus, Star, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import ConfirmButton from '../../components/ui/ConfirmButton.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import IconButton from '../../components/ui/IconButton.vue';
import { useToast } from '../../composables/useToast.js';
import { useProducts } from '../useShop.js';

const props = defineProps({
    product: { type: Object, required: true },
});

const { t } = useI18n();
const toast = useToast();
const { uploadPhoto, deletePhoto, reorderPhotos } = useProducts();

const photos = ref([]);
const sending = ref(0);

watch(() => props.product.photos, (list) => { photos.value = [...list]; }, { immediate: true });

const animation = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 160;

const heading = () => props.product.title || props.product.displayName;

async function send(event) {
    const files = [...event.target.files];
    event.target.value = '';
    if (!files.length) return;

    sending.value = files.length;
    for (const file of files) {
        try {
            await uploadPhoto(props.product.id, file);
            toast.success(t('admin.product.photos.uploaded', { file: file.name }));
        } catch (error) {
            toast.error(t('admin.product.photos.uploadFailed', { file: file.name, error: error.message }));
        }
        sending.value -= 1;
    }
}

function saveOrder(message = null) {
    toast.attempt(() => reorderPhotos(props.product.id, photos.value.map((photo) => photo.id)), message);
}

function move(index, offset) {
    const list = [...photos.value];
    const [photo] = list.splice(index, 1);
    list.splice(index + offset, 0, photo);
    photos.value = list;
    saveOrder(t('admin.product.photos.moved', { position: index + offset + 1 }));
}

function remove(photo) {
    toast.attempt(() => deletePhoto(props.product.id, photo.id), t('admin.product.photos.deleted'));
}
</script>

<template>
    <section class="product-photos" data-test="product-photos">
        <div class="product-photos__head">
            <div>
                <h2 class="product-photos__title">{{ t('admin.product.photos.title') }}</h2>
                <p class="product-photos__hint">{{ t('admin.product.photos.hint') }}</p>
            </div>
            <label :class="['product-photos__upload', { 'product-photos__upload--busy': sending }]">
                <ImagePlus size="1rem" aria-hidden="true" />
                {{ sending ? t('admin.product.photos.uploading', { count: sending }, sending) : t('admin.product.photos.upload') }}
                <input
                    class="visually-hidden"
                    type="file"
                    name="image"
                    accept="image/jpeg,image/png,image/webp,image/avif"
                    multiple
                    :disabled="sending > 0"
                    data-test="photo-upload"
                    @change="send"
                />
            </label>
        </div>
        <p class="product-photos__formats">{{ t('admin.product.photos.formats') }}</p>

        <EmptyState v-if="!photos.length && !sending" :icon="Image" :title="t('admin.product.photos.empty')" :hint="t('admin.product.photos.emptyHint')" />

        <VueDraggable
            v-else
            v-model="photos"
            class="product-photos__grid"
            handle=".product-photos__grip"
            :animation="animation"
            ghost-class="product-photos__ghost"
            @end="saveOrder()"
        >
            <article v-for="(photo, index) in photos" :key="photo.id" :class="['product-photos__card', { 'product-photos__card--cover': index === 0 }]" data-test="photo-card">
                <div class="product-photos__frame">
                    <img class="product-photos__image" :src="photo.thumbUrl" :alt="t('admin.product.photos.alt', { position: index + 1, title: heading() })" :width="photo.thumbWidth" :height="photo.thumbHeight" loading="lazy" />
                    <span class="product-photos__grip" :title="t('admin.product.photos.move', { position: index + 1 })"><GripVertical size="1.125rem" aria-hidden="true" /></span>
                    <span v-if="index === 0" class="product-photos__badge" data-test="photo-cover"><Star size="0.8rem" aria-hidden="true" />{{ t('admin.product.photos.cover') }}</span>
                </div>
                <footer class="product-photos__actions">
                    <IconButton :icon="ArrowUp" :label="t('admin.product.photos.moveUp', { position: index + 1 })" :disabled="index === 0" @click="move(index, -1)" />
                    <IconButton :icon="ArrowDown" :label="t('admin.product.photos.moveDown', { position: index + 1 })" :disabled="index === photos.length - 1" @click="move(index, 1)" />
                    <span class="product-photos__spacer" />
                    <ConfirmButton :icon="Trash2" :label="t('admin.product.photos.delete', { position: index + 1 })" :message="t('admin.product.photos.deleteMessage')" :disabled="sending > 0" @confirm="remove(photo)" />
                </footer>
            </article>
        </VueDraggable>
    </section>
</template>

<style scoped>
.product-photos { display: flex; flex-direction: column; gap: var(--space-3); }
.product-photos__head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: var(--space-3); }
.product-photos__title { font-size: var(--font-size); }
.product-photos__hint, .product-photos__formats { color: var(--color-muted); font-size: var(--font-size-md); }

.product-photos__upload {
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

.product-photos__upload:hover { background: var(--color-accent-strong); }
.product-photos__upload:has(:focus-visible) { outline: 0.125rem solid var(--color-accent); outline-offset: 0.125rem; }
.product-photos__upload--busy { opacity: 0.7; cursor: progress; }

.product-photos__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr)); gap: var(--space-3); }
.product-photos__ghost { opacity: 0.4; }

.product-photos__card { display: flex; flex-direction: column; overflow: hidden; border: 0.0625rem solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.product-photos__card--cover { border-color: var(--color-accent); box-shadow: 0 0 0 0.0625rem var(--color-accent); }
.product-photos__frame { position: relative; aspect-ratio: 1; background: var(--color-bg); }
.product-photos__image { position: absolute; inset: 0; display: block; width: 100%; height: 100%; object-fit: cover; }

.product-photos__grip {
    position: absolute;
    top: var(--space-2);
    left: var(--space-2);
    display: inline-flex;
    padding: var(--space-1);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    color: var(--color-muted);
    cursor: grab;
    touch-action: none;
}

.product-photos__badge {
    position: absolute;
    top: var(--space-2);
    right: var(--space-2);
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    padding: 0.125rem var(--space-2);
    border-radius: var(--radius-pill);
    background: var(--color-accent);
    color: var(--color-on-accent);
    font-size: var(--font-size-xs);
    font-weight: 600;
}

.product-photos__actions { display: flex; align-items: center; gap: var(--space-1); padding: var(--space-2); }
.product-photos__spacer { flex: 1; }
</style>
