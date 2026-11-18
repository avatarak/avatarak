/* API explorer: reads /docs/openapi.json and lets you send real requests. Plain DOM, no dependencies. */
const root = document.getElementById('explorer');
if (root) init(root);

function el(tag, attrs = {}, ...kids) {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'class') n.className = v;
        else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
        else if (v !== false && v != null) n.setAttribute(k, v === true ? '' : v);
    }
    for (const k of kids.flat()) n.append(k instanceof Node ? k : document.createTextNode(String(k)));
    return n;
}

const METHOD = { get: 'bg-emerald-100 text-emerald-800', post: 'bg-blue-100 text-blue-800' };
const SHOW_HEADERS = ['content-type', 'cache-control', 'etag', 'retry-after', 'x-ratelimit-limit', 'x-ratelimit-remaining', 'access-control-allow-origin', 'content-language'];

async function init(root) {
    const T = JSON.parse(root.dataset.i18n);
    let spec;
    try { spec = await (await fetch(root.dataset.spec)).json(); } catch { root.textContent = T.error; return; }
    const origin = location.origin;   // the docs and the API share a host, so requests are same-origin

    const ops = [];
    for (const [path, item] of Object.entries(spec.paths)) {
        for (const [method, op] of Object.entries(item)) ops.push({ path, method, op, secured: (op.security ?? spec.security ?? []).length > 0 });
    }

    // ---- key
    let remembered = '';
    try { remembered = sessionStorage.getItem('avatarak-explorer-key') || ''; } catch { /* storage may be blocked */ }
    const keyInput = el('input', { type: 'password', class: 'field text-start', dir: 'ltr', autocomplete: 'off', spellcheck: 'false', placeholder: 'avk_…', 'aria-label': T.key, value: remembered });
    const remember = el('input', { type: 'checkbox', class: 'size-4 accent-[var(--brand)]' });
    remember.checked = !!remembered;
    const keyBox = el('div', { class: 'rounded-[8px] border border-line p-4' },
        el('label', { class: 'label' }, T.key), keyInput,
        el('p', { class: 'hint mt-1' }, T.keyHelp),
        el('label', { class: 'mt-2 flex items-center gap-2 text-sm' }, remember, T.remember));
    const saveKey = () => { try { remember.checked ? sessionStorage.setItem('avatarak-explorer-key', keyInput.value) : sessionStorage.removeItem('avatarak-explorer-key'); } catch { /* ignore */ } };
    keyInput.addEventListener('input', saveKey); remember.addEventListener('change', saveKey);

    const list = el('ul', { class: 'space-y-1.5', 'aria-label': T.ops });
    const panel = el('div', { class: 'min-w-0 space-y-4' });
    root.replaceChildren(keyBox, el('div', { class: 'mt-6 grid gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]' }, list, panel));

    let current = null;
    const buttons = ops.map((o) => {
        const b = el('button', { type: 'button', class: 'flex w-full items-start gap-2 rounded-[6px] border border-line p-2.5 text-start hover:border-brand', onclick: () => select(o) },
            el('span', { class: `mt-0.5 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase ${METHOD[o.method] || ''}` }, o.method),
            el('span', { class: 'min-w-0' }, el('span', { class: 'block break-all text-sm font-semibold', dir: 'ltr' }, o.path), el('span', { class: 'block text-xs text-muted' }, o.op.summary)));
        list.append(el('li', {}, b));
        return [o, b];
    });

    function select(o) {
        current = o;
        for (const [x, b] of buttons) b.classList.toggle('border-brand', x === o), b.classList.toggle('bg-brand/5', x === o);
        render(o);
    }

    function render(o) {
        const fields = [];
        const params = el('div', { class: 'space-y-3' });
        for (const p of o.op.parameters || []) {
            const s = p.schema || {};
            const def = s.example ?? s.default ?? '';
            const input = s.enum
                ? el('select', { class: 'field', dir: 'ltr' }, el('option', { value: '' }, '—'), s.enum.map((v) => el('option', { value: v, selected: String(v) === String(def) }, v)))
                : el('input', { class: 'field text-start', dir: 'ltr', value: String(def), placeholder: s.pattern ? '' : (s.minimum ? `${s.minimum}–${s.maximum}` : ''), spellcheck: 'false', autocapitalize: 'none' });
            fields.push({ p, input });
            params.append(el('div', {}, el('label', { class: 'label' }, el('code', { dir: 'ltr' }, p.name), p.required ? el('span', { class: 'ms-2 text-xs text-red-600' }, T.required) : '', el('span', { class: 'ms-2 text-xs text-muted' }, p.in)), input,
                p.description ? el('p', { class: 'hint mt-1' }, p.description) : ''));
        }
        const bodySchema = o.op.requestBody?.content?.['application/json'];
        const body = bodySchema ? el('textarea', { class: 'field font-mono text-xs', dir: 'ltr', rows: '8', spellcheck: 'false' }) : null;
        if (body) body.value = JSON.stringify(bodySchema.example ?? {}, null, 2);

        const out = el('div', { class: 'space-y-3' });
        const curl = el('pre', { class: 'overflow-x-auto rounded-[6px] bg-[#101517] p-3 text-start text-xs leading-5 text-white', dir: 'ltr' });
        const sendBtn = el('button', { type: 'button', class: 'btn btn-primary' }, T.send);

        const build = () => {
            let path = o.path; const q = new URLSearchParams();
            for (const { p, input } of fields) {
                const v = input.value.trim();
                if (p.in === 'path') path = path.replace(`{${p.name}}`, encodeURIComponent(v));
                else if (v !== '') q.set(p.name, v);
            }
            return { url: origin + path + (q.toString() ? `?${q}` : ''), path };
        };
        const showCurl = () => {
            const { url } = build();
            const parts = [`curl${o.method === 'post' ? ' -X POST' : ''}`];
            if (o.secured) parts.push('-H "Authorization: Bearer $AVATARAK_KEY"');
            if (body) parts.push('-H "Content-Type: application/json"', `-d '${body.value.replace(/\s*\n\s*/g, ' ').replace(/'/g, "'\\''")}'`);
            parts.push(`"${url}"`);
            curl.textContent = parts.join(' \\\n  ');
        };
        for (const f of fields) f.input.addEventListener('input', showCurl);
        body?.addEventListener('input', showCurl);

        sendBtn.addEventListener('click', async () => {
            out.replaceChildren();
            if (o.secured && !keyInput.value.trim()) { out.append(el('p', { class: 'rounded-[6px] bg-amber-50 p-3 text-sm text-amber-900', role: 'alert' }, T.needKey)); return; }
            const { url } = build();
            const headers = { Accept: 'application/json, image/*;q=0.8, */*;q=0.5' };
            if (o.secured) headers.Authorization = `Bearer ${keyInput.value.trim()}`;
            if (body) headers['Content-Type'] = 'application/json';
            sendBtn.disabled = true; sendBtn.textContent = T.sending;
            const t0 = performance.now();
            try {
                const res = await fetch(url, { method: o.method.toUpperCase(), headers, body: body ? body.value : undefined, credentials: 'omit' });
                const ms = Math.round(performance.now() - t0);
                const ct = res.headers.get('content-type') || '';
                const tone = res.ok ? 'bg-emerald-100 text-emerald-800' : (res.status === 304 ? 'bg-sky-100 text-sky-800' : 'bg-red-100 text-red-800');
                const hs = SHOW_HEADERS.filter((h) => res.headers.has(h)).map((h) => `${h}: ${res.headers.get(h)}`).join('\n');
                let view;
                if (ct.startsWith('image/') && !ct.includes('svg')) view = el('img', { src: URL.createObjectURL(await res.blob()), alt: '', class: 'max-h-64 rounded-[6px] border border-line' });
                else {
                    const text = await res.text();
                    let shown = text; try { if (ct.includes('json')) shown = JSON.stringify(JSON.parse(text), null, 2); } catch { /* not JSON */ }
                    view = el('pre', { class: 'max-h-96 overflow-auto rounded-[6px] bg-soft p-3 text-start text-xs leading-5', dir: 'ltr' }, shown || '—');
                    if (ct.includes('svg') && text.trim().startsWith('<')) view = el('div', { class: 'space-y-2' }, el('img', { src: URL.createObjectURL(new Blob([text], { type: 'image/svg+xml' })), alt: '', class: 'max-h-48 rounded-[6px] border border-line' }), view);
                }
                out.append(el('div', { class: 'flex flex-wrap items-center gap-3' }, el('span', { class: `rounded px-2 py-0.5 text-sm font-bold ${tone}`, dir: 'ltr' }, `${res.status} ${res.statusText}`.trim()), el('span', { class: 'text-xs text-muted' }, `${ms} ms`)),
                    hs ? el('details', {}, el('summary', { class: 'cursor-pointer text-sm text-muted' }, T.headers), el('pre', { class: 'mt-1 overflow-x-auto rounded-[6px] bg-soft p-3 text-start text-xs', dir: 'ltr' }, hs)) : '', view);
            } catch (e) {
                out.append(el('p', { class: 'rounded-[6px] bg-red-50 p-3 text-sm text-red-800', role: 'alert' }, `${T.error}: ${e.message}`));
            } finally { sendBtn.disabled = false; sendBtn.textContent = T.send; }
        });

        panel.replaceChildren(
            el('div', {}, el('div', { class: 'flex flex-wrap items-center gap-2' }, el('span', { class: `rounded px-2 py-0.5 text-xs font-bold uppercase ${METHOD[o.method] || ''}` }, o.method), el('code', { class: 'text-base font-semibold', dir: 'ltr' }, o.path),
                el('span', { class: `rounded-full px-2 py-0.5 text-xs ${o.secured ? 'bg-amber-50 text-amber-900' : 'bg-soft text-ink-2'}` }, o.secured ? T.secured : T.public)), el('p', { class: 'mt-2 text-sm leading-6 text-ink-2' }, o.op.description || '')),
            fields.length ? el('div', {}, el('h3', { class: 'mb-2 font-bold' }, T.params), params) : '',
            body ? el('div', {}, el('h3', { class: 'mb-2 font-bold' }, T.body), body) : '',
            sendBtn, out,
            el('div', {}, el('h3', { class: 'mb-2 font-bold' }, T.curl), curl));
        showCurl();
    }

    select(ops[0]);
}
