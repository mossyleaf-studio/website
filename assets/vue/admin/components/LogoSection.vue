<script setup>
import { ImagePlus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmButton from '../../components/ui/ConfirmButton.vue';
import { useToast } from '../../composables/useToast.js';
import { useTexts } from '../useContent.js';

defineProps({
    logo: { type: Object, default: null },
});

const { t } = useI18n();
const toast = useToast();
const { uploadLogo, removeLogo } = useTexts();

const sending = ref(false);

async function send(event) {
    const [file] = event.target.files;
    event.target.value = '';
    if (!file) return;

    sending.value = true;
    await toast.attempt(() => uploadLogo(file), t('admin.saved'));
    sending.value = false;
}

function remove() {
    toast.attempt(() => removeLogo(), t('admin.texts.logo.removed'));
}
</script>

<template>
    <section class="logo-section" data-test="section-logo">
        <header class="logo-section__header">
            <h2 class="logo-section__title">{{ t('admin.texts.logo.title') }}</h2>
            <a class="logo-section__preview" href="/" target="_blank" rel="noopener">{{ t('admin.texts.seeHome') }}</a>
        </header>
        <div class="logo-section__body">
            <div class="logo-section__frame">
                <img v-if="logo" class="logo-section__image" :src="logo.url" :width="logo.width" :height="logo.height" :alt="t('admin.texts.logo.current')" />
                <span v-else class="logo-section__empty">{{ t('admin.texts.logo.none') }}</span>
            </div>
            <div class="logo-section__actions">
                <p class="logo-section__hint">{{ t('admin.texts.logo.hint') }}</p>
                <div class="logo-section__buttons">
                    <label :class="['logo-section__upload', { 'logo-section__upload--busy': sending }]">
                        <ImagePlus size="1rem" aria-hidden="true" />
                        {{ logo ? t('admin.texts.logo.replace') : t('admin.texts.logo.upload') }}
                        <input class="visually-hidden" type="file" name="logo" accept="image/png,image/webp,image/jpeg,image/avif" :disabled="sending" @change="send" />
                    </label>
                    <ConfirmButton v-if="logo" :icon="Trash2" :label="t('admin.texts.logo.remove')" :message="t('admin.texts.logo.removeMessage')" @confirm="remove" />
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.logo-section {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
    padding: var(--space-5);
    border: 0.0625rem solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
}

.logo-section__header { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: var(--space-2); }
.logo-section__title { font-family: var(--font-display); font-size: 1.25rem; font-weight: 400; }
.logo-section__preview { color: var(--color-muted); font-size: var(--font-size-md); }

.logo-section__body { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-5); }

.logo-section__frame {
    display: grid;
    place-items: center;
    width: 9rem;
    height: 9rem;
    padding: var(--space-2);
    border: 0.0625rem dashed var(--color-border-strong);
    border-radius: var(--radius);
    background: var(--color-bg);
}

.logo-section__image { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; }
.logo-section__empty { color: var(--color-subtle); font-size: var(--font-size-sm); text-align: center; }

.logo-section__actions { display: flex; flex: 1; flex-direction: column; gap: var(--space-3); min-width: 14rem; }
.logo-section__hint { color: var(--color-muted); font-size: var(--font-size-md); }
.logo-section__buttons { display: flex; align-items: center; gap: var(--space-2); }

.logo-section__upload {
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

.logo-section__upload:hover { background: var(--color-accent-strong); }
.logo-section__upload:has(:focus-visible) { outline: 0.125rem solid var(--color-accent); outline-offset: 0.125rem; }
.logo-section__upload--busy { opacity: 0.7; cursor: progress; }
</style>
