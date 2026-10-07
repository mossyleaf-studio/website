<template>
    <header class="studio-header">
        <h1 class="studio-header__wordmark">mossyleaf<wbr />.studio</h1>
        <p class="studio-header__intro">Illustrations of little critters among leaves and moss, drawn by hand.</p>
    </header>
</template>

<style scoped>
.studio-header {
    display: grid;
    gap: var(--space-5);
}

.studio-header__wordmark {
    font-family: var(--font-display);
    font-weight: 400;
    font-size: clamp(3rem, 15vw, 6rem);
    line-height: 0.92;
    letter-spacing: -0.015em;
    color: var(--color-leaf-deep);
}

.studio-header__intro {
    max-width: 26ch;
    font-size: var(--font-size-lg);
    line-height: 1.4;
    color: var(--color-muted);
}
</style>
