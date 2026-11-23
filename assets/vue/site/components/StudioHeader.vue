<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
});

const parts = computed(() => props.name.split(/(?=\.)/));
</script>

<template>
    <header class="studio-header">
        <h1 class="studio-header__wordmark">
            <template v-for="(part, index) in parts" :key="index"><wbr v-if="index" />{{ part }}</template>
        </h1>
    </header>
</template>

<style scoped>
.studio-header {
    --deckle: 1.5rem;
    padding: var(--space-6) var(--gutter) calc(var(--space-6) + var(--deckle));
    color: var(--color-ink);
    background:
        url('../../../styles/site/paper/tooth.svg') 0 0 / 20rem 20rem,
        var(--color-bark);
    mask:
        linear-gradient(#000 0 0) top / 100% calc(100% - var(--deckle) + 0.25rem) no-repeat,
        url('../../../styles/site/paper/deckle.svg') bottom / 37.5rem var(--deckle) repeat-x;
}

.studio-header__wordmark {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2.5rem, 1.75rem + 3.5vw, 4.5rem);
    line-height: 1;
    text-align: center;
    overflow-wrap: break-word;
}

@media (min-width: 56rem) {
    .studio-header {
        padding-block-start: var(--space-7);
    }
}
</style>
