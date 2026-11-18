import { createApp, createSSRApp } from 'vue';
import App from './App.vue';

// A returning visitor who chose the other language lands on its address: "/" -> "/en" (never for crawlers: they have no saved choice).
const brand = window.__BRAND__ || {};
const saved = (() => { try { return localStorage.getItem('locale'); } catch { return null; } })();
if (brand.pageLocale && location.pathname === '/' && ['fa', 'en'].includes(saved) && saved !== brand.pageLocale) {
    location.replace(`/${saved}${location.search}${location.hash}`);
} else {
    // the home page arrives already rendered (see scripts/prerender-landing.mjs): attach to it instead of building it again
    // ...but only when it was made from exactly this code. A stale pre-render (an old build, `npm run dev`, a half-finished
    // deploy) would not line up with the components and would leave a broken page, so it is thrown away and the app starts clean.
    const el = document.getElementById('app');
    const fresh = el.hasAttribute('data-prerendered') && el.dataset.prerenderHash === __LANDING_HASH__;
    if (!fresh) el.innerHTML = '';
    (fresh ? createSSRApp(App) : createApp(App)).mount(el);
}
