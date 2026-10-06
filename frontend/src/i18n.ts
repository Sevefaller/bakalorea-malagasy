import { createI18n } from 'vue-i18n'
import fr from './locales/fr.json'
import en from './locales/en.json'
import mg from './locales/mg.json'
export const i18n = createI18n({ legacy: false, locale: localStorage.getItem('bakalorea.locale') || 'fr', fallbackLocale: 'fr', messages: { fr, en, mg } })
