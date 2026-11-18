<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { adminApi, errorText, fmtDateTime, fmtNum, ago } from '../../admin/lib';
import { t } from '../../i18n';
import { toast, state } from '../../store';
import { copyText } from '../../composables';
import Icon from '../Icon.vue';
import Badge from './Badge.vue';
import UserAvatar from './UserAvatar.vue';
import ConfirmDialog from './ConfirmDialog.vue';

const props = defineProps({ id: { type: Number, required: true } });
const emit = defineEmits(['close', 'changed']);

const u = ref(null); const loading = ref(true); const error = ref('');
const me = computed(() => state.account?.is_admin && u.value?.email === state.account?.email);
const isSelf = computed(() => u.value && state.account && u.value.id === state.account.id);

async function load() {
    loading.value = true; error.value = '';
    try { u.value = await adminApi('GET', `/users/${props.id}`); } catch (e) { error.value = errorText(e); } finally { loading.value = false; }
}
watch(() => props.id, load);
function key(e) { if (e.key === 'Escape' && !dlg.open) emit('close'); }
onMounted(() => { load(); document.addEventListener('keydown', key); });
onBeforeUnmount(() => document.removeEventListener('keydown', key));

/* ------------------------------ actions ------------------------------ */
const dlg = reactive({ open: false, kind: '', busy: false, error: '' });
const open = (kind) => Object.assign(dlg, { open: true, kind, busy: false, error: '' });
const FIELDS = ['bio', 'headline', 'location', 'website', 'links', 'socials', 'names'];

const cfg = computed(() => {
    const k = dlg.kind; const name = u.value?.display || '';
    return ({
        suspend: { title: `${t('a_suspend')}: ${name}`, body: t('a_suspend_text'), reasonLabel: t('a_reason'), danger: true, label: t('a_suspend') },
        unsuspend: { title: `${t('a_unsuspend')}: ${name}`, label: t('a_unsuspend') },
        promote: { title: `${t('a_make_admin')}: ${name}`, body: t('a_make_admin_text'), label: t('a_make_admin') },
        demote: { title: `${t('a_remove_admin')}: ${name}`, body: t('a_remove_admin_text'), danger: true, label: t('a_remove_admin') },
        rename: { title: t('a_rename'), body: t('a_rename_text'), inputLabel: t('a_new_username'), inputValue: u.value?.username || '', label: t('a_rename') },
        avatar: { title: t('a_remove_avatar'), body: t('a_remove_avatar_text'), checks: [{ key: 'all', label: t('a_remove_avatar_all') }], danger: true, label: t('a_remove_avatar') },
        clear: { title: t('a_clear_profile'), body: t('a_clear_text'), checks: FIELDS.map((f) => ({ key: f, label: t('a_f_' + f) })), danger: true, label: t('a_clear_profile') },
        planPro: { title: t('a_plan_grant_pro'), body: t('a_plan_grant_text'), inputLabel: t('a_plan_days'), inputValue: '30', inputType: 'number', label: t('a_plan_grant_pro') },
        planBusiness: { title: t('a_plan_grant_business'), body: t('a_plan_grant_text'), inputLabel: t('a_plan_days'), inputValue: '30', inputType: 'number', label: t('a_plan_grant_business') },
        planEnd: { title: t('a_plan_end'), body: t('a_plan_end_text'), danger: true, label: t('a_plan_end') },
        delete: { title: `${t('a_delete_user')}: ${name}`, body: t('a_delete_text'), typed: u.value?.email, typedLabel: t('a_type_email'), danger: true, label: t('a_delete_confirm') },
    })[k] || {};
});

async function run({ reason, checks, input }) {
    dlg.busy = true; dlg.error = '';
    const id = props.id;
    try {
        switch (dlg.kind) {
            case 'suspend': await adminApi('POST', `/users/${id}/suspend`, { reason }); break;
            case 'unsuspend': await adminApi('POST', `/users/${id}/unsuspend`); break;
            case 'planPro': await adminApi('POST', `/users/${id}/plan`, { plan: 'pro', days: Number(input) }); break;
            case 'planBusiness': await adminApi('POST', `/users/${id}/plan`, { plan: 'business', days: Number(input) }); break;
            case 'planEnd': await adminApi('POST', `/users/${id}/plan`, { plan: 'free' }); break;
            case 'promote': await adminApi('POST', `/users/${id}/admin`, { is_admin: true }); break;
            case 'demote': await adminApi('POST', `/users/${id}/admin`, { is_admin: false }); break;
            case 'rename': await adminApi('POST', `/users/${id}/rename`, { username: input }); break;
            case 'avatar': await adminApi('POST', `/users/${id}/avatar/remove`, { all: !!checks.all }); break;
            case 'clear': {
                const fields = FIELDS.filter((f) => checks[f]);
                if (!fields.length) { dlg.error = t('a_clear_text'); dlg.busy = false; return; }
                await adminApi('POST', `/users/${id}/profile/clear`, { fields }); break;
            }
            case 'delete': await adminApi('DELETE', `/users/${id}`, { confirm: u.value.email }); toast(t('a_done')); dlg.open = false; emit('changed'); emit('close'); return;
        }
        dlg.open = false; toast(t('a_done')); emit('changed'); await load();
    } catch (e) { dlg.error = errorText(e); } finally { dlg.busy = false; }
}
const verifiedEmails = computed(() => (u.value?.emails || []).filter((e) => e.verified).length);
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-40">
            <div class="absolute inset-0 bg-ink/40" @click="emit('close')"></div>
            <aside class="absolute inset-y-0 end-0 flex w-full max-w-[30rem] flex-col bg-white shadow-card" role="dialog" aria-modal="true" :aria-label="t('a_user')">
                <header class="flex items-center justify-between border-b border-line px-5 py-3">
                    <h2 class="font-bold">{{ t('a_user') }} #{{ id }}</h2>
                    <button type="button" class="icon-btn" :aria-label="t('a_close')" @click="emit('close')"><Icon name="close" :size="20" /></button>
                </header>

                <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
                    <p v-if="loading && !u" class="py-10 text-center text-muted">{{ t('a_loading') }}</p>
                    <div v-else-if="error" class="text-center"><p class="text-red-700">{{ error }}</p><button type="button" class="btn btn-ghost mt-3" @click="load">{{ t('a_retry') }}</button></div>

                    <template v-else-if="u">
                        <div class="flex items-center gap-4">
                            <UserAvatar :user="u" :size="64" />
                            <div class="min-w-0">
                                <div class="truncate text-lg font-bold">{{ u.display }}</div>
                                <div dir="ltr" class="truncate text-start text-sm text-muted">@{{ u.username }}</div>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    <Badge v-if="u.suspended" tone="red">{{ t('a_suspended') }}</Badge><Badge v-else tone="green">{{ t('a_active') }}</Badge>
                                    <Badge v-if="u.is_admin" tone="violet">{{ t('a_admin') }}</Badge>
                                </div>
                            </div>
                        </div>

                        <div v-if="u.suspended" class="rounded-[8px] border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                            <div class="font-semibold">{{ t('a_suspended_reason') }}</div>
                            <div>{{ u.suspended_reason }}</div>
                            <div class="mt-1 text-xs opacity-80">{{ t('a_suspended_at') }}: {{ fmtDateTime(u.suspended_at) }}</div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a v-if="u.username && !u.suspended" :href="`/${u.username}`" target="_blank" rel="noopener" class="btn btn-ghost !py-1.5 text-sm"><Icon name="external" :size="16" /> {{ t('a_view_profile') }}</a>
                            <button type="button" class="btn btn-ghost !py-1.5 text-sm" @click="copyText(u.email)"><Icon name="copy" :size="16" /> {{ u.email }}</button>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div><dt class="text-muted">{{ t('a_joined') }}</dt><dd class="font-medium">{{ fmtDateTime(u.created_at) }}</dd></div>
                            <div><dt class="text-muted">{{ t('a_last_login') }}</dt><dd class="font-medium">{{ u.last_login_at ? ago(u.last_login_at) : t('a_never') }}</dd></div>
                        </dl>

                        <section>
                            <h3 class="mb-2 text-sm font-bold">{{ t('a_emails') }} <span class="font-normal text-muted">({{ verifiedEmails }}/{{ u.emails.length }})</span></h3>
                            <ul class="divide-y divide-line rounded-[8px] border border-line">
                                <li v-for="e in u.emails" :key="e.id" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                                    <span dir="ltr" class="min-w-0 truncate">{{ e.email }}</span>
                                    <span class="flex gap-1.5"><Badge v-if="e.primary" tone="blue">{{ t('a_primary') }}</Badge><Badge :tone="e.verified ? 'green' : 'amber'">{{ e.verified ? t('a_verified') : t('a_unverified') }}</Badge><Badge>{{ e.public ? t('a_public') : t('a_private') }}</Badge></span>
                                </li>
                            </ul>
                        </section>

                        <section v-if="u.profile">
                            <h3 class="mb-2 text-sm font-bold">{{ t('a_profile') }}</h3>
                            <dl class="space-y-2 rounded-[8px] border border-line p-3 text-sm">
                                <div v-for="[label, val] in [[t('a_f_names'), [u.profile.name_fa, u.profile.name_en].filter(Boolean).join(' · ')], [t('a_headline'), u.profile.headline], [t('a_location'), u.profile.location], [t('a_website'), u.profile.website]]" :key="label" class="flex gap-3">
                                    <dt class="w-20 shrink-0 text-muted">{{ label }}</dt><dd class="min-w-0 break-words">{{ val || '—' }}</dd>
                                </div>
                                <div class="flex gap-3"><dt class="w-20 shrink-0 text-muted">{{ t('a_bio') }}</dt><dd class="min-w-0 whitespace-pre-line break-words">{{ u.profile.bio || '—' }}</dd></div>
                                <div class="flex gap-3"><dt class="w-20 shrink-0 text-muted">{{ t('a_socials') }}</dt><dd dir="ltr" class="min-w-0 break-words text-start">{{ Object.entries(u.profile.socials || {}).map(([k, v]) => `${k}: ${v}`).join(', ') || '—' }}</dd></div>
                                <div class="flex gap-3"><dt class="w-20 shrink-0 text-muted">{{ t('a_links') }}</dt>
                                    <dd class="min-w-0 space-y-0.5"><template v-if="(u.profile.links || []).length"><div v-for="(l, i) in u.profile.links" :key="i" dir="ltr" class="truncate text-start">{{ l.label }} — {{ l.url }}</div></template><template v-else>—</template></dd></div>
                            </dl>
                        </section>

                        <section>
                            <h3 class="mb-2 text-sm font-bold">{{ t('a_verified_accounts') }}</h3>
                            <p v-if="!u.verified_accounts.length" class="text-sm text-muted">{{ t('a_none') }}</p>
                            <ul v-else class="space-y-1.5 text-sm"><li v-for="a in u.verified_accounts" :key="a.id" class="flex items-center gap-2"><Badge tone="blue">{{ a.network }}</Badge><a :href="a.url" target="_blank" rel="noopener" dir="ltr" class="truncate text-brand hover:underline">{{ a.identifier }}</a></li></ul>
                        </section>

                        <section>
                            <h3 class="mb-2 text-sm font-bold">{{ t('a_keys') }}</h3>
                            <p v-if="!u.sites.length" class="text-sm text-muted">{{ t('a_none') }}</p>
                            <ul v-else class="space-y-1.5 text-sm"><li v-for="s in u.sites" :key="s.id" class="flex items-center justify-between gap-2"><span dir="ltr" class="truncate">{{ s.domain }} <span class="text-muted">· {{ s.prefix }}…</span></span><Badge v-if="s.disabled" tone="red">{{ t('a_disabled') }}</Badge></li></ul>
                        </section>

                        <section>
                            <h3 class="mb-2 text-sm font-bold">{{ t('a_usage_30d') }}</h3>
                            <div class="grid grid-cols-4 gap-2 text-center">
                                <div v-for="m in ['avatar', 'profile', 'export', 'api']" :key="m" class="rounded-[8px] bg-soft py-2"><div class="font-bold tabular-nums">{{ fmtNum(u.stats[m]) }}</div><div class="text-xs text-muted">{{ t('a_m_' + m) }}</div></div>
                            </div>
                            <div class="mt-2 text-sm text-muted">{{ t('a_avatars') }}: {{ fmtNum(u.avatars_count) }} · {{ t('a_reports_against') }}: {{ fmtNum(u.reports.total) }} ({{ fmtNum(u.reports.open) }} {{ t('a_rp_open') }})</div>
                        </section>

                        <section>
                            <h3 class="mb-2 text-sm font-bold">{{ t('a_plan') }}</h3>
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <Badge :tone="u.plan === 'free' ? 'gray' : 'violet'">{{ t('a_plan_' + u.plan) }}</Badge>
                                <span v-if="u.plan_ends_at" class="text-muted">{{ t('a_plan_until') }} {{ fmtDateTime(u.plan_ends_at) }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button type="button" class="btn btn-ghost !py-1.5 text-sm" @click="open('planPro')">{{ t('a_plan_pro') }} +</button>
                                <button type="button" class="btn btn-ghost !py-1.5 text-sm" @click="open('planBusiness')">{{ t('a_plan_business') }} +</button>
                                <button v-if="u.plan !== 'free'" type="button" class="btn btn-ghost !py-1.5 text-sm !text-red-700" @click="open('planEnd')">{{ t('a_plan_end') }}</button>
                            </div>
                        </section>

                        <section v-if="u.audit.length">
                            <h3 class="mb-2 text-sm font-bold">{{ t('a_history') }}</h3>
                            <ul class="space-y-1.5 text-sm"><li v-for="(a, i) in u.audit" :key="i" class="flex flex-wrap justify-between gap-x-3"><span>{{ t('act_' + a.action.replace('.', '_')) }} <span class="text-muted">— {{ a.by }}</span></span><span class="text-xs text-muted">{{ ago(a.at) }}</span></li></ul>
                        </section>

                        <section v-if="!isSelf">
                            <h3 class="mb-2 text-sm font-bold">{{ t('a_moderation') }}</h3>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <button v-if="!u.suspended" type="button" class="btn btn-ghost justify-start" :disabled="u.is_admin" @click="open('suspend')"><Icon name="ban" :size="18" /> {{ t('a_suspend') }}</button>
                                <button v-else type="button" class="btn btn-primary justify-start" @click="open('unsuspend')"><Icon name="userCheck" :size="18" /> {{ t('a_unsuspend') }}</button>
                                <button type="button" class="btn btn-ghost justify-start" @click="open('rename')"><Icon name="user" :size="18" /> {{ t('a_rename') }}</button>
                                <button type="button" class="btn btn-ghost justify-start" :disabled="!u.has_avatar && !u.avatars_count" @click="open('avatar')"><Icon name="image" :size="18" /> {{ t('a_remove_avatar') }}</button>
                                <button type="button" class="btn btn-ghost justify-start" @click="open('clear')"><Icon name="news" :size="18" /> {{ t('a_clear_profile') }}</button>
                                <button v-if="!u.is_admin" type="button" class="btn btn-ghost justify-start" @click="open('promote')"><Icon name="shield" :size="18" /> {{ t('a_make_admin') }}</button>
                                <button v-else type="button" class="btn btn-ghost justify-start" @click="open('demote')"><Icon name="shieldOff" :size="18" /> {{ t('a_remove_admin') }}</button>
                            </div>
                            <p v-if="u.is_admin" class="hint mt-2">{{ t('a_cannot_admin') }}</p>
                        </section>
                        <p v-else class="hint">{{ t('a_cannot_self') }}</p>

                        <section v-if="!isSelf" class="rounded-[8px] border border-red-200 p-4">
                            <h3 class="text-sm font-bold text-red-700">{{ t('a_danger') }}</h3>
                            <button type="button" class="btn mt-3 w-full !bg-red-600 text-white hover:!bg-red-700 disabled:opacity-50" :disabled="u.is_admin" @click="open('delete')"><Icon name="trash" :size="18" /> {{ t('a_delete_user') }}</button>
                        </section>
                    </template>
                </div>
            </aside>
        </div>
    </Teleport>

    <ConfirmDialog :open="dlg.open" :title="cfg.title" :body="cfg.body" :confirm-label="cfg.label" :danger="cfg.danger" :busy="dlg.busy" :error="dlg.error"
                   :reason-label="cfg.reasonLabel" :typed="cfg.typed" :typed-label="cfg.typedLabel" :checks="cfg.checks || []" :input-label="cfg.inputLabel" :input-value="cfg.inputValue || ''"
                   @close="dlg.open = false" @confirm="run" />
</template>
