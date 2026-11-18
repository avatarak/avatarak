import { createApp, createSSRApp } from 'vue';
import App from './App.vue';

// A returning visitor who chose the other language lands on its address: "/" -> "/en" (never for crawlers: they have no saved choice).
const brand = window.__BRAND__ || {};
const saved = (() => { try { return localStorage.getItem('locale'); } catch { return null; } })();
if (brand.pageLocale && location.pathname === '/' && ['fa', 'en'].includes(saved) && saved !== brand.pageLocale) {
    location.replace(`/${saved}${location.search}${location.hash}`);
} else {
    // the home page arrives already rendered (see scripts/prerender-landing.mjs): attach to it instead of building it again
    const el = document.getElementById('app');
    (el.hasAttribute('data-prerendered') ? createSSRApp(App) : createApp(App)).mount(el);
}
