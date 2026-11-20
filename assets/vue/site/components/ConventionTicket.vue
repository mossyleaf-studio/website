<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatRange, stubOf } from '../dates.js';
import PillButton from './PillButton.vue';
import TapeLabel from './TapeLabel.vue';

const props = defineProps({
    convention: { type: Object, required: true },
    label: { type: String, default: null },
});

const { locale } = useI18n();
const range = computed(() => formatRange(props.convention.startsOn, props.convention.endsOn, locale.value));
const stub = computed(() => stubOf(props.convention.startsOn, props.convention.endsOn, locale.value, new Date().getFullYear()));
</script>

<template>
    <article class="ticket" data-test="convention-ticket">
        <TapeLabel v-if="label" class="ticket__label">{{ label }}</TapeLabel>
        <div class="ticket__body">
            <h2 class="ticket__name">{{ convention.name }}</h2>
            <p class="ticket__place">{{ convention.place }}</p>
            <p v-if="convention.note" class="ticket__note">{{ convention.note }}</p>
            <p v-if="convention.url" class="ticket__actions">
                <PillButton :href="convention.url" tone="paper" rel="noopener">{{ $t('conventions.website') }}</PillButton>
            </p>
        </div>
        <time class="ticket__stub" :datetime="convention.startsOn">
            <span class="ticket__days" aria-hidden="true">{{ stub.days }}</span>
            <span class="ticket__month" aria-hidden="true">{{ stub.month }}</span>
            <span v-if="stub.year" class="ticket__year" aria-hidden="true">{{ stub.year }}</span>
            <span class="visually-hidden">{{ range }}</span>
        </time>
    </article>
</template>

<style scoped>
.ticket {
    --stub: 5.75rem;
    --notch: 1.25rem;

    position: relative;
    display: grid;
    grid-template-columns: var(--stub) minmax(0, 1fr);
    background: var(--color-card);
    border: 0.0625rem solid var(--color-edge);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-sheet);
}

.ticket::before,
.ticket::after {
    content: '';
    position: absolute;
    left: var(--stub);
    z-index: 1;
    width: var(--notch);
    height: var(--notch);
    background: var(--color-paper);
    border: 0.0625rem solid var(--color-edge);
    border-radius: 50%;
    translate: -50% 0;
}

.ticket::before {
    top: calc(var(--notch) / -2 - 0.0625rem);
    clip-path: inset(50% 0 0 0);
}

.ticket::after {
    bottom: calc(var(--notch) / -2 - 0.0625rem);
    clip-path: inset(0 0 50% 0);
}

.ticket__stub {
    grid-column: 1;
    grid-row: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: var(--space-1);
    padding: var(--space-5) var(--space-2);
    text-align: center;
    background: var(--color-wash-leaf);
    border-radius: var(--radius-sm) 0 0 var(--radius-sm);
}

.ticket__days {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-xl);
    line-height: 1;
    white-space: nowrap;
}

.ticket__month {
    font-family: var(--font-ui);
    font-size: var(--font-size-sm);
    font-weight: 700;
    line-height: 1.2;
}

.ticket__year {
    font-size: var(--font-size-xs);
    line-height: 1.2;
}

.ticket__body {
    position: relative;
    grid-column: 2;
    grid-row: 1;
    display: grid;
    gap: var(--space-2);
    align-content: start;
    padding: var(--space-5) var(--space-5) var(--space-5) var(--space-5);
}

.ticket__body::before {
    content: '';
    position: absolute;
    inset-block: calc(var(--notch) / 2 + var(--space-1));
    left: -0.3125rem;
    width: 0.625rem;
    background: radial-gradient(circle, var(--color-paper) 0 0.1875rem, var(--color-edge) 0.1875rem 0.25rem, transparent 0.27rem) center top / 0.625rem 0.875rem repeat-y;
}

.ticket__name {
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: var(--font-size-xl);
    line-height: 1.1;
    text-wrap: balance;
}

.ticket__place {
    font-family: var(--font-ui);
    font-weight: 700;
}

.ticket__note {
    max-width: var(--measure);
}

.ticket__actions {
    padding-top: var(--space-3);
}

.ticket__label {
    position: absolute;
    top: -0.875rem;
    right: var(--space-5);
    z-index: 2;
}

@media (min-width: 40rem) {
    .ticket {
        --stub: 9rem;
    }

    .ticket__days {
        font-size: var(--font-size-2xl);
    }

    .ticket__body {
        padding: var(--space-6) var(--space-6) var(--space-6) var(--space-7);
    }
}
</style>
