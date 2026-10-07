<script setup>
import { computed } from 'vue';
import { parseRichText } from '../richText.js';

const props = defineProps({
    text: { type: String, required: true },
});

const segments = computed(() => parseRichText(props.text));
</script>

<template>
    <template v-for="(segment, index) in segments" :key="index">
        <a v-if="segment.href" class="rich-text__link" :href="segment.href">{{ segment.text }}</a>
        <template v-else>{{ segment.text }}</template>
    </template>
</template>

<style>
.rich-text__link {
    color: var(--color-ink);
    text-decoration-thickness: 0.0625rem;
    text-underline-offset: 0.2em;
}

.rich-text__link:hover {
    text-decoration-thickness: 0.125rem;
}
</style>
