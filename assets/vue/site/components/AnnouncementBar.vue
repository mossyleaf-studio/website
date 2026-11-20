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
    font-size: var(--font-size-sm);
    line-height: 1.4;
    text-align: center;
    color: var(--color-ink);
    background: var(--color-meadow);
    border-bottom: 0.0625rem solid var(--color-edge);
}
</style>
