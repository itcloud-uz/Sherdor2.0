import { createI18n } from 'vue-i18n';
import uz from './locales/uz.json';

const i18n = createI18n({
    legacy: false, // Use Vue 3 Composition API
    locale: 'uz',
    fallbackLocale: 'uz',
    messages: {
        uz
    },
});

export async function loadLocaleMessages(locale) {
    // If locale is already loaded, skip
    if (i18n.global.availableLocales.includes(locale)) {
        return;
    }
    try {
        let messages;
        if (locale === 'en') {
            messages = await import('./locales/en.json');
        } else if (locale === 'ru') {
            messages = await import('./locales/ru.json');
        } else if (locale === 'uz') {
            messages = await import('./locales/uz.json');
        }
        if (messages) {
            i18n.global.setLocaleMessage(locale, messages.default);
        }
    } catch (e) {
        console.error('Failed to load locale messages for ' + locale, e);
    }
}

// Retrieve saved language or default to 'uz'
const savedLocale = localStorage.getItem('locale') || 'uz';
if (savedLocale !== 'uz') {
    loadLocaleMessages(savedLocale).then(() => {
        i18n.global.locale.value = savedLocale;
    });
}

export default i18n;
