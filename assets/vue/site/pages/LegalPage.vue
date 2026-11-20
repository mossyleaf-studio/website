<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatRange } from '../dates.js';
import PaperSheet from '../components/PaperSheet.vue';
import RichBlocks from '../components/RichBlocks.vue';

const props = defineProps({
    data: { type: Object, required: true },
});

const { locale } = useI18n();
const updated = computed(() => (props.data.updatedAt ? formatRange(props.data.updatedAt, props.data.updatedAt, locale.value) : null));
</script>

<template>
    <PaperSheet tag="article" class="legal" data-test="legal-page">
        <header class="legal__header">
            <h1 class="legal__title">{{ data.title }}</h1>
            <p v-if="updated" class="legal__updated"><time :datetime="data.updatedAt">{{ $t('legal.updated', { date: updated }) }}</time></p>
        </header>
        <RichBlocks :text="data.text" />
    </PaperSheet>
</template>

<style scoped>
.legal {
    width: min(100%, 44rem);
    justify-self: center;
    margin-inline: auto;
    gap: var(--space-6);
}

.legal > * {
    max-width: 38rem;
}

.legal__header {
    display: grid;
    gap: var(--space-2);
    padding-bottom: var(--space-5);
    border-bottom: 0.0625rem solid var(--color-edge);
}

.legal__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2rem, 1.5rem + 2vw, 2.75rem);
    line-height: 1.08;
    text-wrap: balance;
}

.legal__updated {
    font-size: var(--font-size-sm);
}
</style>
