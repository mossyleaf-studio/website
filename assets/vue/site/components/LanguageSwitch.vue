<script setup>
defineProps({
    languages: { type: Array, required: true },
    landmark: { type: Boolean, default: true },
    tone: { type: String, default: 'paper' },
});
</script>

<template>
    <component :is="landmark ? 'nav' : 'div'" :class="['language-switch', `language-switch--${tone}`]" :aria-label="landmark ? $t('layout.languages') : undefined" data-languages>
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
.language-switch {
    --switch-ink: var(--color-ink);
    --switch-ground: var(--color-paper);
    --switch-current-ink: var(--color-paper);
    --switch-current-ground: var(--color-ink);
}

.language-switch--band {
    --switch-ground: transparent;
}

.language-switch__list {
    display: flex;
    border: 0.0625rem solid var(--switch-ink);
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
    font-weight: 600;
    color: var(--switch-ink);
    text-decoration: none;
    background: var(--switch-ground);
}

.language-switch__link:hover {
    text-decoration: underline;
}

.language-switch__link:focus-visible {
    outline-offset: -0.25rem;
}

.language-switch__link--current {
    color: var(--switch-current-ink);
    background: var(--switch-current-ground);
}

.language-switch__link--current:focus-visible {
    outline-color: var(--color-paper);
}

li + li .language-switch__link {
    border-left: 0.0625rem solid var(--switch-ink);
}
</style>
