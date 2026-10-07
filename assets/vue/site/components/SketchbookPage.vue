<script setup>
import LeafWashes from './LeafWashes.vue';

const year = new Date().getFullYear();
</script>

<template>
    <div class="sketchbook">
        <LeafWashes />

        <main class="sketchbook__page">
            <slot />
        </main>

        <footer class="sketchbook__footer">
            <span class="sketchbook__stamp" aria-hidden="true">m</span>
            <p>© {{ year }} mossyleaf.studio</p>
        </footer>
    </div>
</template>

<style scoped>
.sketchbook {
    position: relative;
    min-height: 100vh;
    overflow-x: clip;
    display: grid;
    grid-template-rows: 1fr auto;
}

.sketchbook__page,
.sketchbook__footer {
    position: relative;
    width: 100%;
    max-width: calc(var(--column) + 2 * var(--gutter));
    margin-inline: auto;
    padding-inline: var(--gutter);
}

.sketchbook__page {
    padding-block: var(--space-8);
    display: grid;
    gap: var(--space-8);
    align-content: start;
}

.sketchbook__footer {
    display: flex;
    align-items: center;
    gap: var(--space-4);
    padding-block: var(--space-6) var(--space-7);
    font-size: var(--font-size-xs);
    color: var(--color-muted);
}

.sketchbook__stamp {
    display: grid;
    place-items: center;
    width: 2.5rem;
    height: 2.5rem;
    font-family: var(--font-display);
    font-size: var(--font-size-lg);
    line-height: 1;
    color: var(--color-fern);
    border: 0.125rem solid var(--color-fern);
    border-radius: 50%;
    rotate: -8deg;
    opacity: 0.85;
    filter: url(#ink-stamp);
}

@media (min-width: 56rem) {
    .sketchbook__page {
        padding-block-start: var(--space-9);
    }
}
</style>
