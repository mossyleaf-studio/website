<script setup>
import { Check } from '@lucide/vue';

defineProps({
    filters: { type: Object, required: true },
});
</script>

<template>
    <nav class="filter-chips" :aria-label="$t(filters.kind === 'collection' ? 'filter.collections' : 'filter.categories')" data-filters>
        <ul class="filter-chips__list">
            <li v-for="item in filters.items" :key="item.key ?? ''">
                <a :class="['filter-chips__chip', { 'filter-chips__chip--current': item.current }]" :href="item.url" :aria-current="item.current ? 'true' : undefined" data-filter>
                    <Check v-if="item.current" class="filter-chips__check" aria-hidden="true" />
                    <span data-filter-label>{{ item.label }}</span>
                    <template v-if="item.hidden">{{ ' ' }}<span class="filter-chips__hidden" data-label>{{ $t('product.hidden') }}</span></template>
                </a>
            </li>
        </ul>
    </nav>
</template>

<style scoped>
.filter-chips__list {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
}

.filter-chips__chip {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    min-height: var(--tap);
    padding: 0 var(--space-4);
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    font-weight: 500;
    line-height: 1.2;
    color: var(--color-ink);
    text-decoration: none;
    background: var(--color-paper);
    border: 0.0625rem solid var(--color-ink);
    border-radius: var(--radius-pill);
    transition: background-color var(--transition);
}

.filter-chips__chip:hover {
    text-decoration: underline;
    text-decoration-thickness: 0.0625rem;
    text-underline-offset: 0.2em;
    background: var(--color-wash-leaf);
}

.filter-chips__chip--current {
    padding-left: var(--space-3);
    font-weight: 600;
    background: var(--color-meadow);
    border: var(--border-strong);
}

.filter-chips__chip--current:hover {
    background: var(--color-meadow);
}

.filter-chips__check {
    width: 1rem;
    height: 1rem;
    flex: none;
}

.filter-chips__hidden {
    font-size: var(--font-size-xs);
    font-weight: 500;
    color: var(--color-muted);
}

.filter-chips__chip:is(:hover, .filter-chips__chip--current) .filter-chips__hidden {
    color: var(--color-ink);
}

@media (forced-colors: active) {
    .filter-chips__chip--current {
        text-decoration: underline;
    }
}
</style>
