import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import { i18n } from './i18n'
import App from './App.vue'
import './style.css'
const router = createRouter({ history: createWebHistory(), routes: [{ path: '/', component: {} }, { path: '/salle/:code', component: {} }, { path: '/:pathMatch(.*)*', redirect: '/' }] })
createApp(App).use(createPinia()).use(router).use(i18n).mount('#app')
if ('serviceWorker' in navigator && import.meta.env.PROD) navigator.serviceWorker.register('/sw.js').catch(() => {})
