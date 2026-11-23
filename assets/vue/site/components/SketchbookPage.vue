<script setup>
defineProps({
    studioName: { type: String, required: true },
});

const year = new Date().getFullYear();
</script>

<template>
    <div class="sketchbook">
        <slot name="band" />

        <main class="sketchbook__page">
            <slot />
        </main>

        <footer class="sketchbook__footer">
            <p>© {{ year }} {{ studioName }}</p>
        </footer>
    </div>
</template>

<style scoped>
.sketchbook {
    min-height: 100vh;
    display: grid;
    grid-template-rows: auto 1fr auto;
}

.sketchbook__page,
.sketchbook__footer {
    width: 100%;
    max-width: calc(var(--column) + 2 * var(--gutter));
    margin-inline: auto;
    padding-inline: var(--gutter);
}

.sketchbook__page {
    padding-block: var(--space-7) var(--space-8);
    display: grid;
    gap: var(--space-7);
    align-content: start;
}

.sketchbook__footer {
    padding-block: var(--space-6) var(--space-7);
    font-size: var(--font-size-xs);
    color: var(--color-muted);
}
</style>
