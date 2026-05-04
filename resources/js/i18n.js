import { createI18n } from 'vue-i18n';

// Import translations
import en from './locales/en.json';
import ru from './locales/ru.json';
import uz from './locales/uz.json';

const messages = {
    en,
    ru,
    uz,
};

// Retrieve saved language or default to 'uz'
const savedLocale = localStorage.getItem('locale') || 'uz';

const i18n = createI18n({
    legacy: false, // Use Vue 3 Composition API
    locale: savedLocale,
    fallbackLocale: 'uz',
    messages,
});

export default i18n;
