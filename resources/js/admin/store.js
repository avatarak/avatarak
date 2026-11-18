import { reactive, ref } from 'vue';

/** Counts shown in the sidebar badge, refreshed whenever the dashboard (or a moderation action) loads them. */
export const adm = reactive({ counts: null });

/** Hash routing: #/users, #/users/12, #/reports … ("params" are one-shot hints for the target page). */
export const route = reactive({ page: 'dashboard', id: null });
export const hint = ref(null);

export function parseHash() {
    const m = location.hash.match(/^#\/([a-z]+)(?:\/(\d+))?/);
    route.page = m ? m[1] : 'dashboard';
    route.id = m && m[2] ? Number(m[2]) : null;
}

export function go(page, h = null, id = null) {
    hint.value = h;
    location.hash = `#/${page}${id ? `/${id}` : ''}`;
}
