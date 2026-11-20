<script setup>
import { inject } from 'vue';
import ConventionTicket from './ConventionTicket.vue';
import PillButton from './PillButton.vue';

defineProps({
    convention: { type: Object, default: null },
});

const url = inject('navigation', []).find((entry) => entry.key === 'conventions')?.url;
</script>

<template>
    <section v-if="convention" class="convention-teaser" :aria-label="$t('conventions.next')" data-next-convention>
        <ConventionTicket class="convention-teaser__ticket" :convention="convention" :label="$t('conventions.next')" />
        <PillButton v-if="url" :href="url">{{ $t('conventions.all') }}</PillButton>
    </section>
</template>

<style scoped>
.convention-teaser {
    display: grid;
    justify-items: center;
    gap: var(--space-6);
    padding-top: var(--space-4);
}

.convention-teaser__ticket {
    width: min(100%, 44rem);
}
</style>
