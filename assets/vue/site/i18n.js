import { createI18n } from 'vue-i18n';
import { countingZeroAsOne } from '../i18n/plural.js';
import en from '../i18n/en/site.json';
import fr from '../i18n/fr/site.json';

export const createSiteI18n = (locale) =>
    createI18n({
        legacy: false,
        locale,
        fallbackLocale: 'en',
        messages: { en, fr },
        pluralRules: { fr: countingZeroAsOne },
    });
