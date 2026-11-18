/* Documentation page enhancements. Everything degrades gracefully: without JS the page is plain readable HTML. */

const body = document.body;
const LABEL = { js: 'JavaScript', javascript: 'JavaScript', node: 'Node.js', php: 'PHP', python: 'Python', py: 'Python', bash: 'cURL', sh: 'Shell', curl: 'cURL', json: 'JSON', http: 'HTTP', html: 'HTML', text: 'Text' };
const langOf = (pre) => (pre.querySelector('code')?.className.match(/language-([\w-]+)/) || [])[1] || 'text';

/* ---------- tiny syntax highlighter (comments, strings, numbers, keywords) ---------- */
const KEYWORDS = /\b(const|let|var|function|async|await|return|new|if|else|for|foreach|while|in|of|as|import|from|export|class|use|namespace|echo|def|print|true|false|null|None|True|False|try|catch|except|throw|public|static)\b/g;
function highlight(code, lang) {
    const comment = ['js', 'javascript', 'node', 'php', 'json'].includes(lang) ? '\\/\\/.*|\\/\\*[\\s\\S]*?\\*\\/' : ['python', 'py', 'bash', 'sh', 'curl'].includes(lang) ? '#.*' : null;
    if (lang === 'json' || lang === 'http' || lang === 'text' || lang === 'html') return lang === 'json' ? highlightWith(code, null, /("(?:[^"\\\n]|\\.)*")(\s*:)?|\b(true|false|null)\b|(-?\d+(?:\.\d+)?)/g, 'json') : esc(code);
    const re = new RegExp((comment ? `(${comment})|` : '()') + '("(?:[^"\\\\\\n]|\\\\.)*"|\'(?:[^\'\\\\\\n]|\\\\.)*\'|`[^`]*`)|(' + KEYWORDS.source + ')|(\\b\\d+(?:\\.\\d+)?\\b)', 'g');
    let out = '', last = 0, m;
    while ((m = re.exec(code))) {
        out += esc(code.slice(last, m.index));
        const cls = m[1] ? 'tok-c' : m[2] ? 'tok-s' : m[3] ? 'tok-k' : 'tok-n';
        out += `<span class="${cls}">${esc(m[0])}</span>`;
        last = m.index + m[0].length;
        if (m[0] === '') re.lastIndex++;
    }
    return out + esc(code.slice(last));
}
function highlightWith(code, _c, re) {
    let out = '', last = 0, m;
    while ((m = re.exec(code))) {
        out += esc(code.slice(last, m.index));
        if (m[1]) out += `<span class="${m[2] ? 'tok-k' : 'tok-s'}">${esc(m[1])}</span>${esc(m[2] || '')}`;
        else out += `<span class="${m[3] ? 'tok-k' : 'tok-n'}">${esc(m[0])}</span>`;
        last = m.index + m[0].length;
    }
    return out + esc(code.slice(last));
}
function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

const article = document.querySelector('[data-docs-article]');
if (article) {
    // highlight first (uses the text, so it must run before DOM is rearranged)
    article.querySelectorAll('pre > code').forEach((code) => {
        const lang = langOf(code.parentElement);
        code.innerHTML = highlight(code.textContent, lang);
    });

    /* ---------- consecutive code blocks become language tabs ---------- */
    const pres = [...article.querySelectorAll(':scope > pre')];
    const seen = new Set();
    pres.forEach((pre) => {
        if (seen.has(pre)) return;
        const group = [pre];
        for (let n = pre.nextElementSibling; n && n.tagName === 'PRE'; n = n.nextElementSibling) group.push(n);
        group.forEach((g) => seen.add(g));
        if (group.length < 2) return;

        const wrap = document.createElement('div');
        wrap.className = 'doc-tabs doc-block';
        const list = document.createElement('div');
        list.setAttribute('role', 'tablist');
        pre.before(wrap);
        wrap.append(list);
        group.forEach((g, i) => {
            const l = langOf(g);
            const tab = document.createElement('button');
            tab.type = 'button'; tab.setAttribute('role', 'tab'); tab.textContent = LABEL[l] || l;
            tab.setAttribute('aria-selected', String(i === 0));
            g.hidden = i !== 0;
            tab.addEventListener('click', () => {
                list.querySelectorAll('[role=tab]').forEach((t) => t.setAttribute('aria-selected', String(t === tab)));
                group.forEach((x) => (x.hidden = x !== g));
            });
            list.append(tab); wrap.append(g);
        });
    });

    /* ---------- copy buttons ---------- */
    article.querySelectorAll('pre').forEach((pre) => {
        const host = pre.closest('.doc-tabs') || pre;
        if (host.classList.contains('doc-block') && host.querySelector(':scope > .doc-copy')) return;
        host.classList.add('doc-block');
        const btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'doc-copy'; btn.textContent = body.dataset.copy || 'Copy';
        btn.addEventListener('click', () => {
            const visible = host.querySelector('pre:not([hidden])') || pre;
            navigator.clipboard?.writeText(visible.textContent.replace(/\n$/, '')).then(() => {
                btn.textContent = body.dataset.copied || 'Copied';
                setTimeout(() => (btn.textContent = body.dataset.copy || 'Copy'), 1500);
            }).catch(() => {});
        });
        host.append(btn);
    });

    /* ---------- "on this page" with scroll-spy ---------- */
    const toc = document.querySelector('[data-docs-toc]');
    if (toc) {
        const heads = [...article.querySelectorAll('h2[id], h3[id]')];
        heads.forEach((h) => {
            const li = document.createElement('li');
            if (h.tagName === 'H3') li.style.paddingInlineStart = '0.9rem';
            const a = document.createElement('a');
            a.href = '#' + h.id; a.className = 'block text-muted hover:text-ink';
            a.textContent = h.childNodes[0].textContent.trim();
            li.append(a); toc.append(li);
        });
        if (!heads.length) toc.closest('aside')?.remove();
        const links = [...toc.querySelectorAll('a')];
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (!e.isIntersecting) return;
                links.forEach((a) => a.removeAttribute('aria-current'));
                toc.querySelector(`a[href="#${CSS.escape(e.target.id)}"]`)?.setAttribute('aria-current', 'true');
            });
        }, { rootMargin: '-80px 0px -70% 0px' });
        heads.forEach((h) => io.observe(h));
    }
}

/* ---------- sidebar search (filters page titles + descriptions) ---------- */
const inputs = document.querySelectorAll('[data-docs-search]');
const items = [...document.querySelectorAll('[data-docs-item]')];
const groups = [...document.querySelectorAll('[data-docs-group]')];
const empty = document.querySelector('[data-docs-empty]');
inputs.forEach((input) => input.addEventListener('input', () => {
    const q = input.value.trim().toLowerCase();
    inputs.forEach((o) => { if (o !== input) o.value = input.value; });
    items.forEach((li) => (li.hidden = q !== '' && !li.dataset.title.includes(q)));
    groups.forEach((g) => (g.hidden = [...g.querySelectorAll('[data-docs-item]')].every((li) => li.hidden)));
    if (empty) empty.hidden = !groups.every((g) => g.hidden);
}));

/* ---------- mobile menu ---------- */
const menu = document.querySelector('[data-docs-menu]');
const nav = document.querySelector('[data-docs-nav]');
menu?.addEventListener('click', () => {
    const open = nav.classList.toggle('hidden') === false;
    menu.setAttribute('aria-expanded', String(open));
    document.body.style.overflow = open ? 'hidden' : '';
});
nav?.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => { if (window.innerWidth < 1024) { nav.classList.add('hidden'); document.body.style.overflow = ''; } }));
