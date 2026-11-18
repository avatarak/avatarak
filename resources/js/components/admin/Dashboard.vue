<script setup>
import { ref, computed, onMounted } from 'vue';
import { adminApi, errorText, fmtNum, ago } from '../../admin/lib';
import { adm, go } from '../../admin/store';
import { t } from '../../i18n';
import Icon from '../Icon.vue';
import Chart from './Chart.vue';
import Badge from './Badge.vue';
import UserAvatar from './UserAvatar.vue';

const data = ref(null); const error = ref(''); const loading = ref(true);
async function load() {
    loading.value = true; error.value = '';
    try { data.value = await adminApi('GET', '/overview'); adm.counts = data.value.counts; } catch (e) { error.value = errorText(e); } finally { loading.value = false; }
}
onMounted(load);

const delta = (now, prev) => (prev ? Math.round(((now - prev) / prev) * 100) : null);
const cards = computed(() => {
    const d = data.value; if (!d) return [];
    const c = d.counts; const p = d.period;
    return [
        { icon: 'users', label: t('a_users'), value: c.users, sub: `+${fmtNum(p.signups)} ${t('a_new_30d')}`, delta: delta(p.signups, p.signups_prev), to: 'users' },
        { icon: 'globe', label: t('a_public_profiles'), value: c.public_profiles, sub: `${fmtNum(c.suspended)} ${t('a_suspended')} · ${fmtNum(c.admins)} ${t('a_admins')}`, to: 'users' },
        { icon: 'image', label: t('a_avatars'), value: c.avatars, sub: `+${fmtNum(p.uploads)} ${t('a_uploads_30d')}`, delta: delta(p.uploads, p.uploads_prev) },
        { icon: 'trend', label: t('a_requests_30d'), value: p.requests, sub: '' },
        { icon: 'verified', label: t('a_verified'), value: c.verified_accounts, sub: Object.entries(c.verified_by_network || {}).map(([n, v]) => `${n} ${fmtNum(v)}`).join(' · '), to: 'verified' },
        { icon: 'key', label: t('a_sites'), value: c.sites, sub: `${fmtNum(c.sites_active_24h)} ${t('a_active_24h')}`, to: 'sites' },
        { icon: 'flag', label: t('a_reports_open'), value: c.reports_open, sub: '', to: 'reports', alert: c.reports_open > 0 },
        { icon: 'ban', label: t('a_suspended'), value: c.suspended, sub: '', to: 'users', filter: 'suspended' },
    ];
});
const growth = computed(() => [{ key: 'signups', label: t('a_signups'), color: '#1D4FC4' }, { key: 'uploads', label: t('a_uploads'), color: '#0F766E' }]);
const traffic = computed(() => [
    { key: 'avatar', label: t('a_m_avatar'), color: '#1D4FC4' }, { key: 'profile', label: t('a_m_profile'), color: '#0F766E' },
    { key: 'export', label: t('a_m_export'), color: '#B45309' }, { key: 'api', label: t('a_m_api'), color: '#7C3AED' },
]);

const warnings = computed(() => {
    const s = data.value?.system; if (!s) return [];
    const w = [];
    if (s.debug && s.env === 'production') w.push(t('a_warn_debug'));
    if (s.mail === 'log' || s.mail === 'array') w.push(t('a_warn_mail'));
    if (s.queue === 'sync' && s.env === 'production') w.push(t('a_warn_queue'));
    if (s.cache === 'array') w.push(t('a_warn_cache'));
    if (s.db === 'sqlite' && s.env === 'production') w.push(t('a_warn_db'));
    return w;
});
const maxRef = computed(() => Math.max(1, ...(data.value?.referrers || []).map((r) => r.count)));
</script>

<template>
    <div class="space-y-6">
        <p v-if="loading && !data" class="py-16 text-center text-muted">{{ t('a_loading') }}</p>
        <div v-else-if="error" class="card p-6 text-center">
            <p class="text-red-700">{{ error }}</p>
            <button type="button" class="btn btn-ghost mt-3" @click="load">{{ t('a_retry') }}</button>
        </div>
        <template v-else-if="data">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <component :is="c.to ? 'button' : 'div'" v-for="c in cards" :key="c.label" :type="c.to ? 'button' : null"
                           class="card p-4 text-start transition-shadow" :class="[c.to ? 'hover:shadow-[0_0_0_1.5px_var(--brand)]' : '', c.alert ? '!shadow-[0_0_0_1.5px_#D97706]' : '']"
                           @click="c.to && go(c.to, c.filter ? { filter: c.filter } : null)">
                    <div class="flex items-center justify-between text-muted"><span class="text-sm">{{ c.label }}</span><Icon :name="c.icon" :size="18" /></div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-bold tabular-nums">{{ fmtNum(c.value) }}</span>
                        <Badge v-if="c.delta != null" :tone="c.delta >= 0 ? 'green' : 'red'"><span dir="ltr">{{ c.delta >= 0 ? '+' : '' }}{{ fmtNum(c.delta) }}%</span></Badge>
                    </div>
                    <div v-if="c.sub" class="mt-1 text-xs text-muted">{{ c.sub }}</div>
                </component>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="card p-5">
                    <h2 class="mb-3 font-bold">{{ t('a_chart_growth') }}</h2>
                    <Chart :rows="data.series" :series="growth" mode="bars" />
                </section>
                <section class="card p-5">
                    <h2 class="mb-3 font-bold">{{ t('a_chart_traffic') }}</h2>
                    <Chart :rows="data.series" :series="traffic" mode="line" />
                </section>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <section class="card overflow-hidden lg:col-span-2">
                    <div class="flex items-center justify-between px-5 py-4"><h2 class="font-bold">{{ t('a_recent_users') }}</h2><button type="button" class="text-sm font-semibold text-brand hover:underline" @click="go('users')">{{ t('a_all') }}</button></div>
                    <ul class="divide-y divide-line border-t border-line">
                        <li v-for="u in data.recent_users" :key="u.id">
                            <button type="button" class="flex w-full items-center gap-3 px-5 py-3 text-start hover:bg-soft" @click="go('users', null, u.id)">
                                <UserAvatar :user="u" />
                                <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ u.display }}</span><span dir="ltr" class="block truncate text-start text-sm text-muted">{{ u.email }}</span></span>
                                <Badge v-if="u.suspended" tone="red">{{ t('a_suspended') }}</Badge>
                                <span class="shrink-0 text-xs text-muted">{{ ago(u.created_at) }}</span>
                            </button>
                        </li>
                    </ul>
                </section>

                <div class="space-y-4">
                    <section class="card p-5">
                        <h2 class="font-bold">{{ t('a_top_sites') }}</h2>
                        <p v-if="!data.referrers.length" class="mt-2 text-sm text-muted">{{ t('a_no_data') }}</p>
                        <ul v-else class="mt-3 space-y-2.5">
                            <li v-for="r in data.referrers" :key="r.host">
                                <div class="flex items-baseline justify-between gap-3 text-sm"><span dir="ltr" class="truncate">{{ r.host }}</span><span class="tabular-nums text-muted">{{ fmtNum(r.count) }}</span></div>
                                <div class="mt-1 h-1.5 rounded-full bg-soft"><div class="h-full rounded-full bg-brand" :style="{ width: (r.count / maxRef) * 100 + '%' }"></div></div>
                            </li>
                        </ul>
                    </section>

                    <section class="card p-5">
                        <div class="flex items-center justify-between"><h2 class="font-bold">{{ t('a_open_reports') }}</h2><button type="button" class="text-sm font-semibold text-brand hover:underline" @click="go('reports')">{{ t('a_all') }}</button></div>
                        <p v-if="!data.open_reports.length" class="mt-2 text-sm text-muted">{{ t('a_rp_empty') }}</p>
                        <ul v-else class="mt-2 divide-y divide-line">
                            <li v-for="r in data.open_reports" :key="r.id" class="flex items-center justify-between gap-2 py-2 text-sm">
                                <span dir="ltr" class="truncate font-medium">@{{ r.profile?.username }}</span>
                                <Badge tone="amber">{{ t('rp_' + r.reason) }}</Badge>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>

            <section class="card p-5">
                <h2 class="font-bold">{{ t('a_system') }}</h2>
                <ul v-if="warnings.length" class="mt-3 space-y-2">
                    <li v-for="w in warnings" :key="w" class="flex items-start gap-2 rounded-[8px] bg-amber-50 px-3 py-2 text-sm text-amber-900"><Icon name="alert" :size="18" class="mt-0.5 shrink-0" /> {{ w }}</li>
                </ul>
                <p v-else class="mt-3 flex items-center gap-2 text-sm text-emerald-800"><Icon name="check" :size="18" /> {{ t('a_all_good') }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3 lg:grid-cols-5" dir="ltr">
                    <div v-for="[k, v] in [[t('a_sys_env'), data.system.env], [t('a_sys_php'), data.system.php], [t('a_sys_laravel'), data.system.laravel], [t('a_sys_db'), data.system.db], [t('a_sys_cache'), data.system.cache],
                        [t('a_sys_queue'), data.system.queue], [t('a_sys_mail'), data.system.mail], [t('a_sys_telegram'), data.system.telegram ? t('a_on') : t('a_off')], [t('a_sys_github'), data.system.github_oauth ? t('a_on') : t('a_off')], [t('a_sys_stats'), data.system.stats ? t('a_on') : t('a_off')]]" :key="k">
                        <dt class="text-muted">{{ k }}</dt><dd class="font-medium">{{ v }}</dd>
                    </div>
                </dl>
            </section>
        </template>
    </div>
</template>
