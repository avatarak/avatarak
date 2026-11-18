// Writes resources/data/landing-seo.json from the landing page copy, so the server can put the same
// headline, features and FAQ into the HTML that search engines read (and into FAQPage structured data).
// Runs automatically before `npm run build`; commit the result.
import { writeFileSync, mkdirSync } from 'node:fs';
import { COPY } from '../resources/js/components/landing/copy-data.js';

const out = {};
for (const [lang, c] of Object.entries(COPY)) {
    out[lang] = {
        title: c.heroTitle, subtitle: c.heroSub,
        features: [[c.syncTitle, c.syncText], [c.profTitle, c.profText], [c.verTitle, c.verText], [c.idTitle, c.idText], [c.privTitle, c.privText], [c.devTitle, c.devText]].filter(([a, b]) => a && b),
        faqTitle: c.faqTitle, faq: c.faq,
    };
}
mkdirSync(new URL('../resources/data/', import.meta.url), { recursive: true });
writeFileSync(new URL('../resources/data/landing-seo.json', import.meta.url), JSON.stringify(out, null, 2) + '\n');
console.log('landing-seo.json written');
