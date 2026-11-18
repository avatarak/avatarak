// A fingerprint of everything the home page is rendered from. The pre-rendered HTML records it, and so does the
// JavaScript bundle; the browser attaches to the pre-rendered page only when the two are identical, so a stale
// pre-render (old build, `npm run dev`, a half-finished deploy) can never produce a broken page: it is simply discarded.
import { createHash } from 'node:crypto';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('../resources/', import.meta.url).pathname;

function walk(dir, out = []) {
    for (const name of readdirSync(dir).sort()) {
        const p = join(dir, name);
        if (statSync(p).isDirectory()) walk(p, out);
        else if (/\.(vue|js|json)$/.test(name) && !/landing-prerender/.test(name)) out.push(p);
    }
    return out;
}

export function landingHash() {
    const h = createHash('sha1');
    for (const dir of ['js', 'data']) {
        for (const f of walk(join(root, dir))) {
            h.update(f.slice(root.length)).update('\0').update(readFileSync(f)).update('\0');
        }
    }

    return h.digest('hex').slice(0, 16);
}
