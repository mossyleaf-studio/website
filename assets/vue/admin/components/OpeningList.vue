<script setup>
import { ChevronDown, CircleAlert, TriangleAlert } from '@lucide/vue';
import { computed, nextTick, ref, useId, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { blockingCount, fixOf, readyCount, shownItems } from '../publication.js';

const props = defineProps({
    readiness: { type: Object, default: null },
    failed: { type: Boolean, default: false },
    shopOpen: { type: Boolean, required: true },
    wide: { type: Boolean, default: false },
});

const { t } = useI18n();
const bodyId = useId();
const root = useTemplateRef('root');
const heading = useTemplateRef('heading');

const expanded = ref(false);
const items = computed(() => shownItems(props.readiness));
const count = computed(() => readyCount(props.readiness));
const blocking = computed(() => blockingCount(props.readiness));
const title = computed(() => t(props.shopOpen ? 'admin.publish.attention' : 'admin.publish.beforeOpening'));
const MARKS = { blocker: CircleAlert, warning: TriangleAlert };

async function reveal() {
    expanded.value = true;
    await nextTick();
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    root.value?.scrollIntoView({ block: 'nearest', behavior: still ? 'auto' : 'smooth' });
    heading.value?.focus({ preventScroll: true });
}

defineExpose({ reveal });
</script>

<template>
    <aside ref="root" :class="['opening-list', { 'opening-list--folded': !wide, 'opening-list--expanded': expanded }]" :aria-labelledby="`${bodyId}-title`" data-test="publish-readiness">
        <h2 ref="heading" class="opening-list__heading" tabindex="-1">
            <button v-if="!wide" type="button" class="opening-list__toggle" :aria-expanded="expanded" :aria-controls="bodyId" @click="expanded = !expanded">
                <span :id="`${bodyId}-title`" class="opening-list__title">{{ title }}</span>
                <span class="opening-list__summary">
                    <span v-if="!shopOpen && readiness" class="opening-list__count tabular" data-test="publish-ready-count">{{ t('admin.publish.readyCount', count) }}</span>
                    <span v-if="blocking > 0" class="opening-list__blocking" data-test="publish-blocking">{{ t('admin.publish.blocking', { count: blocking }, blocking) }}</span>
                </span>
                <ChevronDown class="opening-list__chevron" size="1rem" aria-hidden="true" />
            </button>
            <template v-else>
                <span :id="`${bodyId}-title`" class="opening-list__title">{{ title }}</span>
                <span v-if="!shopOpen && readiness" class="opening-list__count tabular" data-test="publish-ready-count">{{ t('admin.publish.readyCount', count) }}</span>
            </template>
        </h2>
        <div v-show="wide || expanded" :id="bodyId" class="opening-list__body">
            <p v-if="failed && !readiness" class="opening-list__note">{{ t('common.error') }}</p>
            <p v-if="blocking > 0" id="publish-bar-blocked" class="opening-list__note" data-test="publish-blocked">{{ t('admin.publish.blocked', { count: blocking }, blocking) }}</p>
            <ul v-if="items.length > 0" class="opening-list__items">
                <li v-for="item in items" :key="item.key" :class="['opening-list__item', `opening-list__item--${item.state}`]" :data-test="`readiness-${item.key}`" :data-state="item.state">
                    <component :is="MARKS[item.state]" class="opening-list__mark" size="1rem" :stroke-width="2" aria-hidden="true" />
                    <div class="opening-list__text">
                        <p class="opening-list__label">
                            {{ t(`admin.publish.items.${item.key}.label`) }}
                            <span class="visually-hidden">({{ t(`admin.publish.states.${item.state}`) }})</span>
                        </p>
                        <p class="opening-list__problem">{{ t(`admin.publish.items.${item.key}.problem`, { count: item.count ?? 0 }, item.count ?? 0) }}</p>
                        <RouterLink v-if="fixOf(item.key).to" class="opening-list__fix" :to="{ name: fixOf(item.key).to }">{{ t(`admin.publish.items.${item.key}.fix`) }}</RouterLink>
                        <p v-else class="opening-list__server">{{ t(`admin.publish.items.${item.key}.server`) }}</p>
                    </div>
                </li>
            </ul>
        </div>
    </aside>
</template>

<style scoped>
.opening-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    padding: var(--space-4);
    border: 0.0625rem solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
}

.opening-list--folded { gap: 0; padding: 0; }
.opening-list--folded.opening-list--expanded { gap: var(--space-3); padding-bottom: var(--space-4); }

.opening-list__heading { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: var(--space-1) var(--space-3); font-size: var(--font-size); }
.opening-list__heading:focus { outline: none; }
.opening-list__heading:focus-visible { outline: 0.125rem solid var(--color-accent); outline-offset: 0.25rem; }
.opening-list__title { color: var(--color-ink); font-weight: 600; }
.opening-list__count { color: var(--color-muted); font-size: var(--font-size-sm); font-weight: 400; }

.opening-list__toggle {
    display: flex;
    flex: 1;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-1) var(--space-3);
    min-height: 2.75rem;
    padding: var(--space-2) var(--space-4);
    border: none;
    border-radius: var(--radius);
    background: none;
    text-align: left;
    cursor: pointer;
}

.opening-list__toggle:hover { background: var(--color-hover); }
.opening-list__summary { display: flex; flex-wrap: wrap; gap: var(--space-1) var(--space-3); margin-right: auto; }
.opening-list__blocking { color: var(--color-ink); font-size: var(--font-size-sm); font-weight: 600; }
.opening-list__chevron { flex-shrink: 0; color: var(--color-muted); transition: transform var(--transition); }
.opening-list--expanded .opening-list__chevron { transform: rotate(180deg); }

.opening-list__body { display: flex; flex-direction: column; gap: var(--space-3); }
.opening-list--folded .opening-list__body { padding-inline: var(--space-4); }
.opening-list__note { color: var(--color-muted); font-size: var(--font-size-sm); line-height: 1.4; }

.opening-list__items { display: flex; flex-direction: column; margin: 0; padding: 0; list-style: none; }
.opening-list__item { display: flex; align-items: flex-start; gap: var(--space-2); padding: var(--space-3) 0; border-top: 0.0625rem solid var(--color-border); }
.opening-list__mark { flex-shrink: 0; margin-top: 0.125rem; }
.opening-list__item--blocker .opening-list__mark { color: var(--color-danger); }
.opening-list__item--warning .opening-list__mark { color: var(--color-warning); }
.opening-list__text { display: flex; flex-direction: column; gap: 0.125rem; min-width: 0; }
.opening-list__label { color: var(--color-ink); font-size: var(--font-size-md); font-weight: 600; }
.opening-list__problem { color: var(--color-muted); font-size: var(--font-size-sm); line-height: 1.4; }
.opening-list__fix { align-self: flex-start; color: var(--color-accent-strong); font-size: var(--font-size-sm); }
.opening-list__server { color: var(--color-muted); font-size: var(--font-size-sm); line-height: 1.4; overflow-wrap: anywhere; }

@media (min-width: 90rem) {
    .opening-list {
        position: sticky;
        top: var(--space-6);
        width: var(--desk-width);
        max-height: calc(100vh - 2 * var(--space-6));
        overflow-y: auto;
    }
}
</style>
