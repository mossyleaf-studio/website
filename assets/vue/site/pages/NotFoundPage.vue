<script setup>
import PaperSheet from '../components/PaperSheet.vue';
import PillButton from '../components/PillButton.vue';

defineProps({
    home: { type: String, required: true },
});
</script>

<template>
    <div class="not-found">
        <PaperSheet tape="leaf" class="not-found__sheet">
            <h1 class="not-found__title">{{ $t('notFound.title') }}</h1>
            <p>{{ $t('notFound.text') }}</p>
            <p class="not-found__action">
                <PillButton :href="home">{{ $t('notFound.home') }}</PillButton>
            </p>
        </PaperSheet>
    </div>
</template>

<style scoped>
.not-found {
    display: grid;
    place-items: center;
    padding-block: var(--space-7);
}

.not-found__sheet {
    width: min(100%, 32rem);
    justify-items: center;
    text-align: center;
    rotate: -0.75deg;
}

.not-found__title {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-2xl);
    line-height: 1.15;
}

.not-found__action {
    padding-top: var(--space-2);
}
</style>
