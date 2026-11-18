// Renders the home page (both languages) to resources/data/landing-prerender-{fa,en}.html with the real Vue components.
// The brand name and the site address are written as @@TOKENS@@ and filled in per request by resources/views/app.blade.php,
// so a re-branded install gets its own name in the pre-rendered HTML. Runs before `vite build` (npm run build).
// the browser build strips template comments (production); strip them here too, or the two would not line up when hydrating
process.env.NODE_ENV = 'production';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { writeFileSync, mkdirSync } from 'node:fs';

// the browser objects the components read at load time
globalThis.window = globalThis;
globalThis.location = { pathname: '/', search: '', hash: '', origin: '@@ORIGIN@@', host: '@@HOST@@' };
globalThis.__BRAND__ = { name: '@@BRAND_NAME@@', name_en: '@@BRAND_NAME_EN@@', tagline: '@@TAGLINE@@', tagline_en: '@@TAGLINE_EN@@', logo_url: null, locale: 'fa', pageLocale: 'fa' };

const server = await createServer({
    configFile: false, mode: 'production', plugins: [vue({ template: { compilerOptions: { comments: false } } })], appType: 'custom', logLevel: 'error',
    server: { middlewareMode: true }, optimizeDeps: { noDiscovery: true, include: [] },
});
try {
    const { render } = await server.ssrLoadModule('/resources/js/prerender-entry.js');
    mkdirSync(new URL('../resources/data/', import.meta.url), { recursive: true });
    for (const lang of ['fa', 'en']) {
        globalThis.__BRAND__.pageLocale = lang;
        const html = await render(lang);
        writeFileSync(new URL(`../resources/data/landing-prerender-${lang}.html`, import.meta.url), html);
        console.log(`landing-prerender-${lang}.html: ${(html.length / 1024).toFixed(0)} KB`);
    }
} finally {
    await server.close();
}
