<script setup>
defineProps({
    text: { type: String, required: true },
});
</script>

<template>
    <p v-if="text" class="announcement" data-announcement>{{ text }}</p>
</template>

<style scoped>
.announcement {
    padding: var(--space-2) var(--page-gutter);
    font-family: var(--font-ui);
    font-size: var(--font-size-xs);
    font-weight: 500;
    line-height: 1.4;
    text-align: center;
    color: var(--color-ink);
    background: var(--color-meadow);
}
</style>
