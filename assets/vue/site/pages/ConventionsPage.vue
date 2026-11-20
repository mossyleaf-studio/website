<script setup>
import ConventionTicket from '../components/ConventionTicket.vue';
import PaperSheet from '../components/PaperSheet.vue';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <div class="conventions">
        <h1 class="conventions__title">{{ data.title }}</h1>
        <ul v-if="data.conventions.length" class="conventions__list" data-conventions>
            <li v-for="(convention, index) in data.conventions" :key="convention.id">
                <ConventionTicket :convention="convention" :label="index === 0 ? $t('conventions.soon') : null" />
            </li>
        </ul>
        <PaperSheet v-else class="conventions__empty" data-test="conventions-empty">
            <p>{{ $t('conventions.empty') }}</p>
        </PaperSheet>
    </div>
</template>

<style scoped>
.conventions {
    display: grid;
    gap: var(--space-7);
}

.conventions__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: clamp(2.125rem, 1.5rem + 2.5vw, var(--font-size-3xl));
    line-height: 1.08;
}

.conventions__list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 34rem), 1fr));
    gap: var(--space-7) var(--grid-gap);
}

.conventions__empty {
    width: min(100%, 38rem);
}
</style>
