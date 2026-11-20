import { createI18n } from 'vue-i18n';
import { currentLanguage } from './locale.js';
import { countingZeroAsOne } from './plural.js';

const files = import.meta.glob(['./*/*.json', '!./*/site.json'], { eager: true, import: 'default' });

const messages = {};
for (const [path, content] of Object.entries(files)) {
    const [, language, namespace] = path.match(/^\.\/([a-z]{2})\/([a-zA-Z-]+)\.json$/);
    messages[language] = { ...messages[language], [namespace]: content };
}

export const i18n = createI18n({
    legacy: false,
    locale: currentLanguage(),
    fallbackLocale: 'en',
    messages,
    pluralRules: { fr: countingZeroAsOne },
});

export const t = (...args) => i18n.global.t(...args);
