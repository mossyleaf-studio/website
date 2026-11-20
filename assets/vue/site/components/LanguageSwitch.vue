<script setup>
defineProps({
    languages: { type: Array, required: true },
    landmark: { type: Boolean, default: true },
});
</script>

<template>
    <component :is="landmark ? 'nav' : 'div'" class="language-switch" :aria-label="landmark ? $t('layout.languages') : undefined" data-languages>
        <ul class="language-switch__list">
            <li v-for="language in languages" :key="language.locale">
                <a
                    :class="['language-switch__link', { 'language-switch__link--current': language.current }]"
                    :href="language.url"
                    :hreflang="language.locale"
                    :lang="language.locale"
                    :aria-current="language.current ? 'true' : undefined"
                >
                    <span aria-hidden="true">{{ $t(`language.code.${language.locale}`) }}</span>
                    <span class="visually-hidden">{{ $t(`language.name.${language.locale}`) }}</span>
                </a>
            </li>
        </ul>
    </component>
</template>

<style scoped>
.language-switch__list {
    display: flex;
    border: 0.0625rem solid var(--color-ink);
    border-radius: var(--radius-pill);
    overflow: hidden;
}

.language-switch__link {
    display: grid;
    place-items: center;
    min-width: var(--tap);
    min-height: var(--tap);
    padding-inline: var(--space-3);
    font-family: var(--font-ui);
    font-size: var(--font-size-xs);
    font-weight: 700;
    color: var(--color-ink);
    text-decoration: none;
    background: var(--color-paper);
}

.language-switch__link:hover {
    text-decoration: underline;
}

.language-switch__link:focus-visible {
    outline-offset: -0.25rem;
}

.language-switch__link--current {
    background: var(--color-leaf);
}

li + li .language-switch__link {
    border-left: 0.0625rem solid var(--color-ink);
}
</style>
