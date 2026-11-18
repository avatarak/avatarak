import { reactive } from 'vue';
import { state } from './store';

/**
 * Per-language editing. Every translatable text is stored twice: the "base" field (written in primary_lang) and
 * "<field>_alt" (the other language). `k('headline')` says which of the two the editor is working on right now.
 * Mirrors App\Models\Profile::pick() / localized().
 */
export const lang = reactive({ tab: null });

export const mode = () => state.draft?.languages || 'fa';
export const primary = () => state.draft?.primary_lang || 'fa';
export const editLang = () => (mode() === 'both' ? lang.tab || primary() : mode());
export const k = (field) => (editLang() === primary() ? field : field + '_alt');
export const dirOf = () => (editLang() === 'en' ? 'ltr' : 'rtl');

/** One text in a language; a bilingual profile falls back to the other, a single-language one never does. */
export function pick(p, base, alt, l) {
    const prim = p.primary_lang || 'fa';
    const own = l === prim ? base : alt;
    const other = l === prim ? alt : base;
    return own || (p.languages === 'both' ? other : '') || '';
}

export const previewLang = (p) => (p.languages === 'both' ? editLang() : p.languages || 'fa');

/** A copy of the draft whose texts are already in language l (what the public page will show). */
export function localize(p, l) {
    const g = (f) => pick(p, p[f], p[f + '_alt'], l);
    return {
        ...p,
        headline: g('headline'), location: g('location'), bio: g('bio'),
        links: (p.links || []).map((x) => ({ ...x, label: pick(p, x.label, x.label_alt, l), description: pick(p, x.description, x.description_alt, l) })).filter((x) => x.label),
        custom_sections: (p.custom_sections || []).map((s) => ({ title: pick(p, s.title, s.title_alt, l), body: pick(p, s.body, s.body_alt, l) })).filter((s) => s.title && s.body),
    };
}
