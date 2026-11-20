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
import StatusBadge from '../../components/ui/StatusBadge.vue';
import { useToast } from '../../composables/useToast.js';
import { currentLanguage, dateFormat } from '../../i18n/locale.js';
import { canPublish, canShowNote, fixOf, noteMessage, publishingMessage, readyCount, shopHref, shownItems, stateOf } from '../publication.js';
import { usePublication, useReadiness } from '../useContent.js';

const { t } = useI18n();
const toast = useToast();
const { publication, load, publish } = usePublication();
const { readiness, load: loadReadiness } = useReadiness();
const confirming = ref(null);
const publishing = ref(false);
const readinessFailed = ref(false);

function refreshReadiness() {
    loadReadiness().then(() => { readinessFailed.value = false; }, () => { readinessFailed.value = true; });
}

load();
refreshReadiness();

const TONES = { ok: 'success', blocker: 'danger', warning: 'warning' };

const dateFormatter = dateFormat({ dateStyle: 'medium', timeStyle: 'short' });
const state = computed(() => stateOf(publication.value, (iso) => dateFormatter.format(new Date(iso))));
const items = computed(() => shownItems(readiness.value));
const count = computed(() => readyCount(readiness.value));
const noteEffect = computed(() => noteMessage(publication.value));
const blockerCount = computed(() => readiness.value?.items.filter((item) => item.state === 'blocker').length ?? 0);
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
    try {
        await toast.attempt(() => publish(page), t(`admin.publish.${done}`));
    } finally {
        publishing.value = false;
    }
    refreshReadiness();
}

function askToPublish() {
    if (publishable.value) confirming.value = 'full';
}
</script>

<template>
    <section v-if="publication" :class="['publish-bar', { 'publish-bar--pending': publication.pendingChanges }]" data-test="publish-bar">
        <div class="publish-bar__status">
            <p class="publish-bar__state" data-test="publish-state" :data-open="publication.open">{{ t(`admin.publish.state.${state.key}`, state.params) }}</p>
            <p class="publish-bar__changes">
                {{ publication.pendingChanges ? t('admin.publish.pending') : t('admin.publish.upToDate') }}
            </p>
            <p v-if="blockerCount > 0" id="publish-bar-blocked" class="publish-bar__changes publish-bar__changes--blocked" data-test="publish-blocked">{{ t('admin.publish.blocked', { count: blockerCount }, blockerCount) }}</p>
            <p v-else id="publish-bar-effect" class="publish-bar__changes" data-test="publish-effect">{{ t(`admin.publish.effect.${publishingMessage(publication)}`) }}</p>
            <p v-if="noteEffect" class="publish-bar__changes" data-test="note-effect">{{ t(`admin.publish.effect.${noteEffect}`) }}</p>
            <p class="publish-bar__changes" data-test="publish-scope">{{ t('admin.publish.scope') }}</p>
        </div>
        <div class="publish-bar__actions">
            <a v-if="preview" class="publish-bar__preview" :href="preview" target="_blank" rel="noopener" data-test="publish-preview"><Eye size="0.95rem" aria-hidden="true" />{{ t('admin.publish.preview') }}</a>
            <BaseButton v-if="canShowNote(publication)" variant="ghost" data-test="show-note" @click="confirming = 'note'">{{ t('admin.publish.showNote') }}</BaseButton>
            <BaseButton :loading="publishing" :aria-disabled="!publishable || undefined" :aria-describedby="blockerCount > 0 ? 'publish-bar-blocked' : 'publish-bar-effect'" data-test="publish-action" @click="askToPublish">
                <Send size="0.95rem" aria-hidden="true" />{{ t('admin.publish.publish') }}
            </BaseButton>
        </div>

        <div v-if="readinessFailed && !readiness" class="publish-bar__readiness" data-test="publish-readiness">
            <p class="publish-bar__blocked">{{ t('common.error') }}</p>
        </div>
        <div v-else-if="readiness && (!publication.open || items.length > 0)" class="publish-bar__readiness" data-test="publish-readiness">
            <p class="publish-bar__heading">
                {{ t(publication.open ? 'admin.publish.attention' : 'admin.publish.beforeOpening') }}
                <span v-if="!publication.open" class="publish-bar__count" data-test="publish-ready-count">{{ t('admin.publish.readyCount', count) }}</span>
            </p>
            <ul v-if="items.length > 0" class="publish-bar__items">
                <li v-for="item in items" :key="item.key" class="publish-bar__item" :data-test="`readiness-${item.key}`" :data-state="item.state">
                    <StatusBadge :tone="TONES[item.state]">{{ t(`admin.publish.states.${item.state}`) }}</StatusBadge>
                    <div class="publish-bar__item-text">
                        <p class="publish-bar__item-label">{{ t(`admin.publish.items.${item.key}.label`) }}</p>
                        <p class="publish-bar__item-detail">{{ t(`admin.publish.items.${item.key}.problem`, { count: item.count ?? 0 }, item.count ?? 0) }}</p>
                        <RouterLink v-if="fixOf(item.key).to" class="publish-bar__fix" :to="{ name: fixOf(item.key).to }">{{ t(`admin.publish.items.${item.key}.fix`) }}</RouterLink>
                        <p v-else class="publish-bar__item-detail">{{ t(`admin.publish.items.${item.key}.server`) }}</p>
                    </div>
                </li>
            </ul>
        </div>

        <AlertDialogRoot :open="confirming !== null" @update:open="(open) => !open && (confirming = null)">
            <AlertDialogPortal>
                <AlertDialogOverlay class="modal">
                    <AlertDialogContent class="modal__panel">
                        <AlertDialogTitle class="modal__title">
                            {{ t(`admin.publish.${confirmation}Title`) }}
                        </AlertDialogTitle>
                        <AlertDialogDescription class="publish-bar__message">
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
.publish-bar__changes--blocked { color: var(--color-ink); }

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

.publish-bar__readiness { flex-basis: 100%; display: flex; flex-direction: column; gap: var(--space-2); padding-top: var(--space-3); border-top: 0.0625rem solid var(--color-border); }
.publish-bar__heading { color: var(--color-ink); font-weight: 600; font-size: var(--font-size-md); }
.publish-bar__count { margin-left: var(--space-2); color: var(--color-muted); font-weight: 400; font-size: var(--font-size-sm); }
.publish-bar__blocked { color: var(--color-muted); font-size: var(--font-size-sm); }
.publish-bar__items { display: grid; gap: var(--space-2) var(--space-5); margin: 0; padding: 0; list-style: none; grid-template-columns: repeat(auto-fill, minmax(min(100%, 22rem), 1fr)); }
.publish-bar__item { display: flex; align-items: flex-start; gap: var(--space-2); }
.publish-bar__item-text { display: flex; flex-direction: column; gap: 0.125rem; min-width: 0; }
.publish-bar__item-label { color: var(--color-ink); font-size: var(--font-size-md); }
.publish-bar__item-detail { color: var(--color-muted); font-size: var(--font-size-sm); }
.publish-bar__fix { color: var(--color-ink); font-size: var(--font-size-sm); text-decoration: underline; }

@media (max-width: 40rem) {
    .publish-bar { padding-inline: var(--space-4); }
}
</style>
