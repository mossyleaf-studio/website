<script setup>
defineProps({
    items: { type: Array, required: true },
});
</script>

<template>
    <nav class="breadcrumb" :aria-label="$t('layout.breadcrumb')" data-breadcrumb>
        <ol class="breadcrumb__list">
            <li v-for="(item, index) in items" :key="index" class="breadcrumb__item">
                <a v-if="item.url" class="breadcrumb__link" :href="item.url">{{ item.label }}</a>
                <span v-else class="breadcrumb__current" aria-current="page">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>

<style scoped>
.breadcrumb__list {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    margin: 0;
    padding: 0;
    font-size: var(--font-size-sm);
    list-style: none;
}

.breadcrumb__item {
    display: flex;
    align-items: center;
    min-width: 0;
}

.breadcrumb__item + .breadcrumb__item::before {
    content: '/' / '';
    padding-inline: var(--space-2);
    color: var(--color-muted);
}

.breadcrumb__link {
    display: inline-flex;
    align-items: center;
    min-height: var(--tap);
    color: var(--color-ink);
    text-underline-offset: 0.25em;
}

.breadcrumb__current {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--color-muted);
}
</style>
