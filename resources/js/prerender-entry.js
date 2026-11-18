// Build-time only (see scripts/prerender-landing.mjs): renders the home page to HTML so it is on screen
// as soon as the HTML and CSS arrive, long before the JavaScript has downloaded. The browser then hydrates it.
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import App from './App.vue';
import { i18n } from './i18n';

export async function render(locale) {
    i18n.locale = locale;
    return renderToString(createSSRApp(App));
}
