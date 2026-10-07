import { createApp, h } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory, RouterView } from 'vue-router'
import { i18n } from './i18n'
import App from './App.vue'
import Admin from './Admin.vue'
import './style.css'
const router = createRouter({ history: createWebHistory(), routes: [{ path: '/', component: App }, { path: '/salle/:code', component: App }, { path: '/admin', component: Admin }, { path: '/:pathMatch(.*)*', redirect: '/' }] })
createApp({ render: () => h(RouterView) }).use(createPinia()).use(router).use(i18n).mount('#app')
if ('serviceWorker' in navigator && import.meta.env.PROD) navigator.serviceWorker.register('/sw.js').catch(() => {})
