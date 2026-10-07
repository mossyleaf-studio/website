<script setup>
import { Eye, Send } from '@lucide/vue';
import {
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'reka-ui';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import { useToast } from '../../composables/useToast.js';
import { intlLocale } from '../../i18n/locale.js';
import { usePublication } from '../useContent.js';

const { t } = useI18n();
const toast = useToast();
const { publication, load, publish } = usePublication();
load();

const confirming = ref(null);
const publishing = ref(false);

const status = computed(() => {
    const current = publication.value;
    if (!current?.publishedAt) return t('admin.publish.never');

    const when = new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(current.publishedAt));
    return t(current.page === 'note' ? 'admin.publish.noteSince' : 'admin.publish.fullSince', { when, by: current.publishedBy });
});

async function confirm() {
    const page = confirming.value;
    confirming.value = null;
    publishing.value = true;
    await toast.attempt(() => publish(page), t(page === 'note' ? 'admin.publish.noteDone' : 'admin.publish.fullDone'));
    publishing.value = false;
}
</script>

<template>
    <section v-if="publication" :class="['publish-bar', { 'publish-bar--pending': publication.pendingChanges }]" data-test="publish-bar">
        <div class="publish-bar__status">
            <p class="publish-bar__state">{{ status }}</p>
            <p class="publish-bar__changes">
                {{ publication.pendingChanges ? t('admin.publish.pending') : t('admin.publish.upToDate') }}
            </p>
        </div>
        <div class="publish-bar__actions">
            <a class="publish-bar__preview" href="/beta/" target="_blank" rel="noopener"><Eye size="0.95rem" aria-hidden="true" />{{ t('admin.publish.preview') }}</a>
            <BaseButton variant="ghost" @click="confirming = 'note'">{{ t('admin.publish.showNote') }}</BaseButton>
            <BaseButton :loading="publishing" :disabled="!publication.pendingChanges && publication.page === 'full'" @click="confirming = 'full'">
                <Send size="0.95rem" aria-hidden="true" />{{ t('admin.publish.publish') }}
            </BaseButton>
        </div>

        <AlertDialogRoot :open="confirming !== null" @update:open="(open) => !open && (confirming = null)">
            <AlertDialogPortal>
                <AlertDialogOverlay class="modal">
                    <AlertDialogContent class="modal__panel">
                        <AlertDialogTitle class="modal__title">
                            {{ confirming === 'note' ? t('admin.publish.confirmNoteTitle') : t('admin.publish.confirmFullTitle') }}
                        </AlertDialogTitle>
                        <AlertDialogDescription class="publish-bar__message">
                            {{ confirming === 'note' ? t('admin.publish.confirmNote') : t('admin.publish.confirmFull') }}
                        </AlertDialogDescription>
                        <div class="actions-row">
                            <AlertDialogCancel as-child><BaseButton variant="ghost">{{ t('common.cancel') }}</BaseButton></AlertDialogCancel>
                            <BaseButton @click="confirm">{{ confirming === 'note' ? t('admin.publish.showNote') : t('admin.publish.publish') }}</BaseButton>
                        </div>
                    </AlertDialogContent>
                </AlertDialogOverlay>
            </AlertDialogPortal>
        </AlertDialogRoot>
    </section>
</template>

<style scoped>
.publish-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3) var(--space-5);
    padding: var(--space-3) var(--space-5);
    border-bottom: 0.0625rem solid var(--color-border);
    background: var(--color-bg);
}

.publish-bar--pending { background: var(--color-warning-soft); }

.publish-bar__status { display: flex; flex-direction: column; gap: 0.125rem; }
.publish-bar__state { color: var(--color-ink); font-weight: 600; font-size: var(--font-size-md); }
.publish-bar__changes { color: var(--color-muted); font-size: var(--font-size-sm); }

.publish-bar__actions { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); }

.publish-bar__preview {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    margin-right: var(--space-2);
    color: var(--color-muted);
    font-size: var(--font-size-md);
}

.publish-bar__message { margin: 0; color: var(--color-muted); }

@media (max-width: 40rem) {
    .publish-bar { padding-inline: var(--space-4); }
}
</style>
