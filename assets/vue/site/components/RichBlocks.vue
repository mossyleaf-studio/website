<script setup>
import { computed } from 'vue';
import { parseRichBlocks } from '../richBlocks.js';

const props = defineProps({
    text: { type: String, required: true },
});

const blocks = computed(() => parseRichBlocks(props.text));
</script>

<template>
    <div class="rich-blocks" data-blocks>
        <template v-for="(block, index) in blocks" :key="index">
            <ul v-if="block.type === 'list'" class="rich-blocks__list">
                <li v-for="(item, position) in block.items" :key="position" class="rich-blocks__item">
                    <template v-for="(segment, part) in item" :key="part">
                        <a v-if="segment.href" class="rich-text__link" :href="segment.href">{{ segment.text }}</a>
                        <template v-else>{{ segment.text }}</template>
                    </template>
                </li>
            </ul>
            <component :is="block.type === 'heading' ? 'h2' : 'p'" v-else :class="`rich-blocks__${block.type}`">
                <template v-for="(segment, part) in block.segments" :key="part">
                    <a v-if="segment.href" class="rich-text__link" :href="segment.href">{{ segment.text }}</a>
                    <template v-else>{{ segment.text }}</template>
                </template>
            </component>
        </template>
    </div>
</template>

<style scoped>
.rich-blocks {
    display: grid;
    gap: var(--space-4);
}

.rich-blocks__heading {
    padding-top: var(--space-4);
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-lg);
    line-height: 1.2;
}

.rich-blocks__heading:first-child {
    padding-top: 0;
}

.rich-blocks__paragraph {
    white-space: pre-line;
}

.rich-blocks__list {
    display: grid;
    gap: var(--space-2);
}

.rich-blocks__item {
    position: relative;
    padding-left: var(--space-5);
}

.rich-blocks__item::before {
    content: '';
    position: absolute;
    top: 0.62em;
    left: var(--space-2);
    width: 0.4375rem;
    height: 0.4375rem;
    background: var(--color-leaf);
    border: 0.0625rem solid var(--color-ink);
    border-radius: 50%;
}
</style>
