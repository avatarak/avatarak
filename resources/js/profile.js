import QRCode from 'qrcode';

/* ---------- bio "Show more" (only when the 3-line clamp really hides text) ---------- */
document.querySelectorAll('[data-bio]').forEach((box) => {
    const text = box.querySelector('[data-bio-text]');
    const btn = box.querySelector('[data-bio-toggle]');
    const measure = () => {
        const open = !text.classList.contains('line-clamp-3');
        text.classList.add('line-clamp-3');
        const over = text.scrollHeight > text.clientHeight + 1;
        if (open) text.classList.remove('line-clamp-3');
        btn.classList.toggle('hidden', !over && !open);
    };
    btn.addEventListener('click', () => {
        const open = text.classList.toggle('line-clamp-3') === false;
        btn.textContent = open ? btn.dataset.less : btn.dataset.more;
    });
    measure();
    window.addEventListener('resize', measure);
});

/* ---------- copy helpers ---------- */
function flash(btn, msg) {
    const label = btn.textContent;
    btn.textContent = msg;
    setTimeout(() => (btn.textContent = label), 1600);
}
document.querySelectorAll('[data-share]').forEach((b) => b.addEventListener('click', () => {
    navigator.clipboard?.writeText(location.href).then(() => flash(b, b.dataset.copied));
}));
document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', () => {
    navigator.clipboard?.writeText(b.dataset.copy).then(() => flash(b.querySelector('span') || b, b.dataset.copied));
}));

/* ---------- QR (client side: no data leaves the browser) ---------- */
function qrSvg(text, avatarDataUri) {
    const qr = QRCode.create(text, { errorCorrectionLevel: 'H' });
    const n = qr.modules.size, data = qr.modules.data, m = 2, total = n + m * 2;
    let d = '';
    for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) if (data[y * n + x]) d += `M${x + m} ${y + m}h1v1h-1z`;
    let center = '';
    if (avatarDataUri) {
        const c = total / 2, r = total * 0.115;
        center = `<defs><clipPath id="qc"><circle cx="${c}" cy="${c}" r="${r}"/></clipPath></defs>`
            + `<circle cx="${c}" cy="${c}" r="${r + 0.9}" fill="#fff"/>`
            + `<image href="${avatarDataUri}" x="${c - r}" y="${c - r}" width="${r * 2}" height="${r * 2}" clip-path="url(#qc)" preserveAspectRatio="xMidYMid slice"/>`;
    }
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${total} ${total}" shape-rendering="crispEdges">`
        + `<rect width="${total}" height="${total}" fill="#fff"/><path d="${d}" fill="#101517"/>${center}</svg>`;
}
async function toDataUri(url) {
    const blob = await (await fetch(url)).blob();
    return new Promise((res, rej) => { const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(blob); });
}

/* ---------- "Advanced details" modal ---------- */
const root = document.getElementById('adv');
if (root) {
    const backdrop = root.querySelector('.pm-backdrop');
    const panel = root.querySelector('.pm-panel');
    const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),[tabindex]:not([tabindex="-1"])';
    let opener = null, pressedOnBackdrop = false, prev = '';

    const trap = (e) => {
        if (e.key === 'Escape') { e.stopPropagation(); close(); return; }
        if (e.key !== 'Tab') return;
        const items = [...panel.querySelectorAll(FOCUSABLE)].filter((el) => el.offsetParent !== null);
        if (!items.length) return;
        const [first, last] = [items[0], items[items.length - 1]];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    };
    function open(from) {
        opener = from;
        const sb = window.innerWidth - document.documentElement.clientWidth;
        prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        if (sb > 0) document.body.style.paddingInlineEnd = sb + 'px';
        root.hidden = false;
        root.classList.remove('is-closing');
        void root.offsetWidth;                       // commit the hidden state so the transition runs
        root.classList.add('is-open');
        document.addEventListener('keydown', trap, true);
        panel.focus({ preventScroll: true });
    }
    function close() {
        root.classList.remove('is-open');
        root.classList.add('is-closing');
        document.removeEventListener('keydown', trap, true);
        setTimeout(() => {
            if (root.classList.contains('is-open')) return;
            root.hidden = true;
            document.body.style.overflow = prev;
            document.body.style.paddingInlineEnd = '';
            opener?.focus?.({ preventScroll: true });
        }, 170);
    }
    function showQr() {
        if (qrWrap.hidden) { qrWrap.hidden = false; draw(); }
        qrWrap.scrollIntoView({ block: 'nearest' });
    }
    const wireOpeners = () => document.querySelectorAll('[data-adv-open]').forEach((b) => b.addEventListener('click', () => {
        open(b);
        if (b.hasAttribute('data-adv-qr')) showQr();
    }));
    root.querySelectorAll('[data-adv-close]').forEach((b) => b.addEventListener('click', close));
    // closes only when the press AND the release both happen on the backdrop (never on a text-selection drag)
    backdrop.addEventListener('pointerdown', (e) => { pressedOnBackdrop = e.target === backdrop; });
    backdrop.addEventListener('click', (e) => { if (pressedOnBackdrop && e.target === backdrop) close(); pressedOnBackdrop = false; });

    /* QR panel */
    const qrBox = root.querySelector('[data-qr-box]');
    const qrWrap = root.querySelector('[data-qr]');
    const qrUrl = qrWrap?.dataset.url;
    const avatarUrl = qrWrap?.dataset.avatar;
    let mode = 'plain', svg = '';
    async function draw() {
        try {
            svg = qrSvg(qrUrl, mode === 'avatar' && avatarUrl ? await toDataUri(avatarUrl) : null);
        } catch { svg = qrSvg(qrUrl, null); }
        qrBox.innerHTML = svg;
    }
    root.querySelectorAll('[data-qr-toggle]').forEach((b) => b.addEventListener('click', () => {
        qrWrap.hidden = !qrWrap.hidden;
        if (!qrWrap.hidden) draw();
    }));
    root.querySelectorAll('[data-qr-mode]').forEach((b) => b.addEventListener('click', () => {
        mode = b.dataset.qrMode;
        root.querySelectorAll('[data-qr-mode]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
        draw();
    }));
    wireOpeners();
    root.querySelector('[data-qr-download]')?.addEventListener('click', () => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
        a.download = `${qrWrap.dataset.name}-qr.svg`;
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    });
}


/* ---------- toast ---------- */
let toastTimer;
function toast(text, tone = 'ok') {
    const el = document.getElementById('toast');
    if (!el) return;
    el.dataset.tone = tone;
    el.querySelector('[data-toast-text]').textContent = text;
    el.querySelector('[data-toast-icon]').innerHTML = tone === 'ok'
        ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg>'
        : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v5m0 3.5v.01M10.3 3.9 2.4 17.5A2 2 0 0 0 4.1 20.5h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>';
    el.hidden = false;
    requestAnimationFrame(() => el.classList.add('is-in'));
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.classList.remove('is-in'); setTimeout(() => (el.hidden = true), 250); }, 3800);
}

/* ---------- custom dropdown (replaces the browser's native <select>) ---------- */
document.querySelectorAll('[data-select]').forEach((root) => {
    const btn = root.querySelector('[data-select-btn]');
    const list = root.querySelector('[data-select-list]');
    const input = root.querySelector('input[type=hidden]');
    const label = root.querySelector('[data-select-label]');
    const opts = [...list.querySelectorAll('[role=option]')];
    let cur = Math.max(0, opts.findIndex((o) => o.getAttribute('aria-selected') === 'true'));

    const choose = (i) => {
        cur = i;
        opts.forEach((o, k) => o.setAttribute('aria-selected', String(k === i)));
        input.value = opts[i].dataset.value;
        label.textContent = opts[i].textContent.trim();
    };
    const isOpen = () => !list.hidden;
    const close = (refocus) => { list.hidden = true; btn.setAttribute('aria-expanded', 'false'); if (refocus) btn.focus(); };
    const open = () => { list.hidden = false; btn.setAttribute('aria-expanded', 'true'); opts[cur].focus(); };
    const move = (d) => { cur = (cur + d + opts.length) % opts.length; opts[cur].focus(); };

    btn.addEventListener('click', () => (isOpen() ? close() : open()));
    btn.addEventListener('keydown', (e) => { if (['ArrowDown', 'ArrowUp'].includes(e.key)) { e.preventDefault(); open(); } });
    opts.forEach((o, i) => {
        o.addEventListener('click', () => { choose(i); close(true); });
        o.addEventListener('mousemove', () => o.focus({ preventScroll: true }));
    });
    list.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
        else if (e.key === 'Home') { e.preventDefault(); cur = 0; opts[0].focus(); }
        else if (e.key === 'End') { e.preventDefault(); cur = opts.length - 1; opts[cur].focus(); }
        else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(opts.indexOf(document.activeElement)); close(true); }
        else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(true); }
        else if (e.key === 'Tab') close();
    });
    document.addEventListener('click', (e) => { if (isOpen() && !root.contains(e.target)) close(); });
    root.closest('dialog')?.addEventListener('cancel', (e) => { if (isOpen()) { e.preventDefault(); close(true); } });
    root.closest('dialog')?.addEventListener('close', () => close());
});

/* ---------- report this profile ---------- */
(() => {
    const dlg = document.getElementById('report-dialog');
    if (!dlg) return;
    const form = dlg.querySelector('[data-report-form]');
    const send = dlg.querySelector('[data-report-send]');
    const xsrf = () => decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    document.querySelectorAll('[data-report-open]').forEach((b) => b.addEventListener('click', () => { send.disabled = false; dlg.showModal(); }));
    dlg.querySelector('[data-report-close]').addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        send.disabled = true; const label = send.textContent; send.textContent = form.dataset.sending;
        try {
            const res = await fetch(form.dataset.url, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf() },
                body: JSON.stringify({ reason: form.reason.value, details: form.details.value.trim() || null }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'fail');
            form.details.value = '';
            dlg.close();
            toast(data.message, 'ok');
        } catch {
            toast(form.dataset.fail, 'error');
            send.disabled = false;
        }
        send.textContent = label;
    });
})();
