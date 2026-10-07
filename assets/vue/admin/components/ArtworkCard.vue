<script setup>
import { ArrowDown, ArrowUp, GripVertical, Star, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import ConfirmButton from '../../components/ui/ConfirmButton.vue';
import FormField from '../../components/ui/FormField.vue';
import IconButton from '../../components/ui/IconButton.vue';
import { useToast } from '../../composables/useToast.js';
import { LIMITS } from '../limits.js';
import { useArtworks } from '../useContent.js';

const props = defineProps({
    artwork: { type: Object, required: true },
    first: { type: Boolean, default: false },
    last: { type: Boolean, default: false },
});

const emit = defineEmits(['move']);

const { t } = useI18n();
const toast = useToast();
const artworks = useArtworks();

const alt = ref(props.artwork.alt);
const savingAlt = ref(false);
watch(() => props.artwork.alt, (value) => { alt.value = value; });

async function saveAlt() {
    savingAlt.value = true;
    await toast.attempt(() => artworks.describe(props.artwork.id, alt.value), t('admin.saved'));
    savingAlt.value = false;
}

function toggleFeatured() {
    toast.attempt(() => (props.artwork.featured ? artworks.unfeature(props.artwork.id) : artworks.feature(props.artwork.id)));
}

function remove() {
    toast.attempt(() => artworks.remove(props.artwork.id), t('admin.images.deleted'));
}
</script>

<template>
    <article :class="['artwork-card', { 'artwork-card--featured': artwork.featured }]" data-test="artwork-card">
        <div class="artwork-card__frame">
            <img class="artwork-card__image" :src="artwork.url" :alt="artwork.alt" :width="artwork.width" :height="artwork.height" loading="lazy" />
            <span class="artwork-card__grip" :title="t('admin.images.move')"><GripVertical size="1.125rem" aria-hidden="true" /></span>
            <span v-if="artwork.featured" class="artwork-card__badge"><Star size="0.8rem" aria-hidden="true" />{{ t('admin.images.featured') }}</span>
        </div>
        <form class="artwork-card__body" @submit.prevent="saveAlt">
            <FormField :label="t('admin.images.alt')" :hint="t('admin.images.altHint')">
                <textarea v-model="alt" rows="2" name="alt" :maxlength="LIMITS.alt" />
            </FormField>
            <BaseButton v-if="alt !== artwork.alt" type="submit" variant="secondary" :loading="savingAlt">{{ t('admin.images.saveAlt') }}</BaseButton>
        </form>
        <footer class="artwork-card__actions">
            <BaseButton variant="secondary" :aria-pressed="artwork.featured" @click="toggleFeatured">
                <Star size="0.95rem" aria-hidden="true" />{{ artwork.featured ? t('admin.images.unfeature') : t('admin.images.feature') }}
            </BaseButton>
            <span class="artwork-card__spacer" />
            <IconButton :icon="ArrowUp" :label="t('admin.moveUp')" :disabled="first" @click="emit('move', -1)" />
            <IconButton :icon="ArrowDown" :label="t('admin.moveDown')" :disabled="last" @click="emit('move', 1)" />
            <ConfirmButton :icon="Trash2" :label="t('admin.images.delete')" :message="t('admin.images.deleteMessage')" @confirm="remove" />
        </footer>
    </article>
</template>

<style scoped>
.artwork-card {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 0.0625rem solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
}

.artwork-card--featured { border-color: var(--color-accent); box-shadow: 0 0 0 0.0625rem var(--color-accent); }

.artwork-card__frame { position: relative; background: var(--color-bg); }
.artwork-card__image { display: block; width: 100%; height: 12rem; object-fit: contain; }

.artwork-card__grip {
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

.artwork-card__badge {
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

.artwork-card__body { display: flex; flex-direction: column; align-items: flex-start; gap: var(--space-2); padding: var(--space-3) var(--space-4) 0; }
.artwork-card__body :deep(.form-field) { width: 100%; }
.artwork-card__body textarea { min-height: 3.5rem; }
.artwork-card__actions { display: flex; align-items: center; gap: var(--space-1); margin-top: auto; padding: var(--space-3) var(--space-4); }
.artwork-card__spacer { flex: 1; }
</style>
