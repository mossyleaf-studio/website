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
import { computed, onBeforeUnmount, ref, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import { useToast } from '../../composables/useToast.js';
import { currentLanguage, dateFormat } from '../../i18n/locale.js';
import { blockingCount, canPublish, canShowNote, listShown, noteMessage, publishingMessage, shopHref, stateOf } from '../publication.js';
import OpeningList from './OpeningList.vue';
import { usePublication, useReadiness } from '../useContent.js';

const { t } = useI18n();
const toast = useToast();
const { publication, load, publish } = usePublication();
const { readiness, load: loadReadiness } = useReadiness();
const confirming = ref(null);
const publishing = ref(false);
const readinessFailed = ref(false);
const list = useTemplateRef('list');

const WIDE = window.matchMedia('(min-width: 90rem)');
const wide = ref(WIDE.matches);
const follow = (event) => { wide.value = event.matches; };
WIDE.addEventListener('change', follow);
onBeforeUnmount(() => WIDE.removeEventListener('change', follow));

const refreshReadiness = () => loadReadiness().then(() => { readinessFailed.value = false; }, () => { readinessFailed.value = true; });

load();
refreshReadiness();

const dateFormatter = dateFormat({ dateStyle: 'medium', timeStyle: 'short' });
const state = computed(() => stateOf(publication.value, (iso) => dateFormatter.format(new Date(iso))));
const noteEffect = computed(() => noteMessage(publication.value));
const blockerCount = computed(() => blockingCount(readiness.value));
const shownList = computed(() => listShown(publication.value, readiness.value, readinessFailed.value));
const publishable = computed(() => canPublish(publication.value, readiness.value));
const confirmation = computed(() => {
    if (confirming.value === 'note') return publication.value.open ? 'confirmNote' : 'confirmNoteRefresh';
    return publication.value.open ? 'confirmUpdate' : 'confirmFull';
});
const preview = computed(() => shopHref(publication.value, currentLanguage()));

async function confirm() {
    const page = confirming.value;
    const done = page === 'note' ? 'noteDone' : publication.value.open ? 'updateDone' : 'fullDone';
    confirming.value = null;
    publishing.value = true;
    let published = false;
    try {
        published = await toast.attempt(() => publish(page), t(`admin.publish.${done}`));
    } finally {
        publishing.value = false;
    }
    await refreshReadiness();
    if (!published) list.value?.reveal();
}

function askToPublish() {
    if (publishable.value) confirming.value = 'full';
    else list.value?.reveal();
}
</script>

<template>
    <div class="publish" data-test="publish-bar">
        <section v-if="publication" :class="['publish__row', { 'publish__row--pending': publication.pendingChanges }]" :aria-label="t('admin.publish.label')">
            <div class="publish__status">
                <p class="publish__state" data-test="publish-state" :data-open="publication.open">{{ t(`admin.publish.state.${state.key}`, state.params) }}</p>
                <p class="publish__changes">
                    {{ publication.pendingChanges ? t('admin.publish.pending') : t('admin.publish.upToDate') }}
                    <span class="publish__scope" data-test="publish-scope">{{ t('admin.publish.scope') }}</span>
                </p>
                <p id="publish-bar-effect" class="visually-hidden" data-test="publish-effect">{{ t(`admin.publish.effect.${publishingMessage(publication)}`) }}</p>
                <p v-if="noteEffect" id="publish-bar-note" class="visually-hidden" data-test="note-effect">{{ t(`admin.publish.effect.${noteEffect}`) }}</p>
            </div>
            <div class="publish__actions">
                <a v-if="preview" class="publish__preview" :href="preview" target="_blank" rel="noopener" data-test="publish-preview"><Eye size="0.95rem" aria-hidden="true" />{{ t('admin.publish.preview') }}</a>
                <BaseButton v-if="canShowNote(publication)" variant="ghost" aria-describedby="publish-bar-note" data-test="show-note" @click="confirming = 'note'">{{ t('admin.publish.showNote') }}</BaseButton>
                <BaseButton :loading="publishing" :aria-disabled="!publishable || undefined" :aria-describedby="blockerCount > 0 ? 'publish-bar-blocked' : 'publish-bar-effect'" data-test="publish-action" @click="askToPublish">
                    <Send size="0.95rem" aria-hidden="true" />{{ t('admin.publish.publish') }}
                </BaseButton>
            </div>
        </section>

        <OpeningList v-if="shownList && !wide" ref="list" class="publish__list" :readiness="readiness" :failed="readinessFailed" :shop-open="publication.open" />

        <slot />

        <OpeningList v-if="shownList && wide" ref="list" class="publish__list" :readiness="readiness" :failed="readinessFailed" :shop-open="publication.open" wide />

        <AlertDialogRoot v-if="publication" :open="confirming !== null" @update:open="(open) => !open && (confirming = null)">
            <AlertDialogPortal>
                <AlertDialogOverlay class="modal">
                    <AlertDialogContent class="modal__panel">
                        <AlertDialogTitle class="modal__title">
                            {{ t(`admin.publish.${confirmation}Title`) }}
                        </AlertDialogTitle>
                        <AlertDialogDescription class="publish__message">
                            {{ t(`admin.publish.${confirmation}`) }}
                        </AlertDialogDescription>
                        <div class="actions-row">
                            <AlertDialogCancel as-child><BaseButton variant="ghost">{{ t('common.cancel') }}</BaseButton></AlertDialogCancel>
                            <BaseButton data-test="publish-confirm" @click="confirm">{{ confirming === 'note' ? t('admin.publish.showNote') : t('admin.publish.publish') }}</BaseButton>
                        </div>
                    </AlertDialogContent>
                </AlertDialogOverlay>
            </AlertDialogPortal>
        </AlertDialogRoot>
    </div>
</template>

<style scoped>
.publish { display: contents; }

.publish__row {
    grid-area: publish;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3) var(--space-5);
    margin-bottom: var(--space-5);
    padding: var(--space-3) var(--space-4);
    border: 0.0625rem solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
}

.publish__row--pending { border-color: var(--color-warning); background: var(--color-warning-soft); }

.publish__status { display: flex; flex: 1 1 24rem; flex-direction: column; gap: 0.125rem; min-width: 0; }
.publish__state { color: var(--color-ink); font-weight: 600; font-size: var(--font-size-md); }
.publish__changes { color: var(--color-muted); font-size: var(--font-size-sm); line-height: 1.4; }

.publish__actions { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); }

.publish__preview {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    margin-right: var(--space-2);
    color: var(--color-muted);
    font-size: var(--font-size-md);
}

.publish__message { margin: 0; color: var(--color-muted); }

.publish__list { grid-area: list; margin-bottom: var(--space-5); }

@media (max-width: 53.75rem) {
    .publish__row { margin-bottom: var(--space-4); }
    .publish__scope { display: none; }
    .publish__list { margin-bottom: var(--space-4); }
}

@media (min-width: 90rem) {
    .publish__list { margin: 0 0 0 var(--space-5); }
}
</style>
