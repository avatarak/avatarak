<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import { api, ApiError } from '../../api';
import { state, refresh, toast } from '../../store';
import { copyText } from '../../composables';
import { t } from '../../i18n';
import Icon from '../Icon.vue';
import SocialIcon from '../SocialIcon.vue';

const accounts = ref([]);
const tg = reactive({ enabled: false, bot: '' });
const gh = reactive({ oauth: false });
const li = reactive({ enabled: false });
const firstError = (e) => (e instanceof ApiError ? (Object.values(e.errors)[0]?.[0] || e.message) : t('error'));

async function load() {
    const r = await api('GET', '/account/verifications');
    accounts.value = r.accounts;
    tg.enabled = r.telegram.enabled;
    tg.bot = r.telegram.bot;
    gh.oauth = !!r.github?.oauth;
    li.enabled = !!r.linkedin?.enabled;
}
async function changed() { await load(); await refresh(); }   // refresh() updates the live preview too

/* ---------------- website ---------------- */
const hostOf = (u) => { try { return new URL(/^https?:\/\//.test(u) ? u : `https://${u}`).host.replace(/^www\./, ''); } catch { return ''; } };
const domain = ref(hostOf(state.draft.website || ''));
const instr = ref(null);
const webBusy = ref(false);
const webMsg = ref('');
const webErr = ref('');
const websiteVerified = computed(() => accounts.value.find((a) => a.network === 'website' && a.identifier === hostOf(domain.value)));

async function startWebsite() {
    webErr.value = ''; webMsg.value = ''; webBusy.value = true;
    try {
        const r = await api('POST', '/account/verifications/website', { domain: domain.value });
        r.verified ? await changed() : (instr.value = r.instructions);
    } catch (e) { webErr.value = firstError(e); } finally { webBusy.value = false; }
}
async function checkWebsite() {
    webErr.value = ''; webMsg.value = ''; webBusy.value = true;
    try {
        const r = await api('POST', '/account/verifications/website/check', { domain: domain.value });
        if (r.verified) { instr.value = null; toast(t('vDone')); await changed(); } else { webMsg.value = r.message; }
    } catch (e) { webErr.value = firstError(e); } finally { webBusy.value = false; }
}

/* ---------------- telegram ---------------- */
const handle = ref(state.draft.socials?.telegram || '');
const tgLink = ref('');
const tgErr = ref('');
const tgBusy = ref(false);
let timer = null;
const telegramVerified = computed(() => accounts.value.find((a) => a.network === 'telegram'));

function stopPolling() { clearInterval(timer); timer = null; }
async function startTelegram() {
    tgErr.value = ''; tgBusy.value = true;
    try {
        const r = await api('POST', '/account/verifications/telegram', { handle: handle.value });
        if (r.verified) { await changed(); return; }
        tgLink.value = r.link;
        stopPolling();
        let tries = 0;
        timer = setInterval(async () => {
            await load();
            if (telegramVerified.value) { stopPolling(); tgLink.value = ''; toast(t('vDone')); await refresh(); }
            else if (++tries > 90) { stopPolling(); }            // ~3 minutes
        }, 2000);
    } catch (e) { tgErr.value = firstError(e); } finally { tgBusy.value = false; }
}

/* ---------------- github ---------------- */
const ghHandle = ref(state.draft.socials?.github || '');
const ghInstr = ref(null);
const ghBusy = ref(false);
const ghMsg = ref('');
const ghErr = ref('');
const githubVerified = computed(() => accounts.value.find((a) => a.network === 'github'));

async function startGithub() {
    ghErr.value = ''; ghMsg.value = ''; ghBusy.value = true;
    try {
        const r = await api('POST', '/account/verifications/github', { handle: ghHandle.value });
        r.verified ? await changed() : (ghInstr.value = r.instructions);
    } catch (e) { ghErr.value = firstError(e); } finally { ghBusy.value = false; }
}
async function checkGithub() {
    ghErr.value = ''; ghMsg.value = ''; ghBusy.value = true;
    try {
        const r = await api('POST', '/account/verifications/github/check', { handle: ghHandle.value });
        if (r.verified) { ghInstr.value = null; toast(t('vDone')); await changed(); } else { ghMsg.value = r.message; }
    } catch (e) { ghErr.value = firstError(e); } finally { ghBusy.value = false; }
}
const netName = (n) => ({ website: t('vWebsite'), telegram: t('vTelegram'), github: t('vGithub'), linkedin: t('vLinkedin') }[n] || n);
const linkedinVerified = computed(() => accounts.value.find((a) => a.network === 'linkedin'));

async function remove(a) {
    if (!confirm(t('confirmDelete'))) return;
    await api('DELETE', `/account/verifications/${a.id}`);
    await changed();
}

onMounted(load);
onBeforeUnmount(stopPolling);
</script>

<template>
    <div class="space-y-8">
        <!-- verified list -->
        <section>
            <p v-if="!accounts.length" class="text-muted">{{ t('vNone') }}</p>
            <ul v-else class="space-y-2">
                <li v-for="a in accounts" :key="a.id" class="flex items-center gap-3 rounded-[8px] border border-line p-3">
                    <span class="grid size-9 shrink-0 place-items-center"><SocialIcon v-if="a.network !== 'website'" :name="a.network" :size="18" /><Icon v-else name="globe" :size="18" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-1.5 text-sm font-semibold">
                            {{ netName(a.network) }}
                            <svg width="16" height="16" viewBox="0 0 24 24" aria-label="verified" class="text-[#3B6BF0]"><path fill="currentColor" d="M12 1.5l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3 1 3-2.6 1.9-1 3-3.2-.1L12 22.5l-2.6-1.9-3.2.1-1-3-2.6-1.9 1-3-1-3 2.6-1.9 1-3 3.2.1z"/><path fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M8 12.3l2.7 2.7L16 9.6"/></svg>
                        </span>
                        <span dir="ltr" class="block truncate text-start text-sm text-muted">{{ a.network === 'linkedin' ? (a.label || '') : (a.network === 'telegram' || a.network === 'github' ? '@' : '') + a.identifier }}</span>
                    </span>
                    <button type="button" class="icon-btn text-muted hover:text-red-700" :title="t('vRemove')" :aria-label="t('vRemove')" @click="remove(a)"><Icon name="trash" :size="16" /></button>
                </li>
            </ul>
        </section>

        <!-- website -->
        <section>
            <h3 class="flex items-center gap-2 text-lg font-bold"><Icon name="globe" :size="20" /> {{ t('vWebsite') }}</h3>
            <form class="mt-3 flex flex-col gap-2" @submit.prevent="startWebsite">
                <input v-model="domain" dir="ltr" class="field text-start" :placeholder="t('vDomain')" :aria-label="t('vDomain')" inputmode="url" autocapitalize="none" spellcheck="false">
                <button class="btn btn-primary" :disabled="webBusy || !domain || websiteVerified">{{ websiteVerified ? t('vDone') : t('vStart') }}</button>
            </form>
            <p v-if="webErr" class="mt-2 text-sm text-red-600" role="alert">{{ webErr }}</p>

            <div v-if="instr" class="mt-4 space-y-4">
                <p class="hint">{{ t('vAnyOne') }}</p>

                <div class="rounded-[8px] border border-line p-3">
                    <div class="text-sm font-semibold">1 · {{ t('vMeta') }}</div>
                    <p class="hint mt-1">{{ t('vMetaHelp') }}</p>
                    <div class="mt-2 flex items-start gap-2">
                        <code dir="ltr" class="min-w-0 flex-1 break-all rounded-[4px] bg-soft p-2 text-start text-xs leading-5">{{ instr.meta }}</code>
                        <button type="button" class="icon-btn shrink-0" :aria-label="t('copy')" @click="copyText(instr.meta)"><Icon name="copy" :size="16" /></button>
                    </div>
                </div>

                <div class="rounded-[8px] border border-line p-3">
                    <div class="text-sm font-semibold">2 · {{ t('vFile') }}</div>
                    <p class="hint mt-1">{{ t('vFileHelp') }}</p>
                    <code dir="ltr" class="mt-2 block break-all rounded-[4px] bg-soft p-2 text-start text-xs leading-5">{{ instr.file.path }}</code>
                    <div class="mt-2 flex items-start gap-2">
                        <code dir="ltr" class="min-w-0 flex-1 break-all rounded-[4px] bg-soft p-2 text-start text-xs leading-5">{{ instr.file.content }}</code>
                        <button type="button" class="icon-btn shrink-0" :aria-label="t('copy')" @click="copyText(instr.file.content)"><Icon name="copy" :size="16" /></button>
                    </div>
                </div>

                <div class="rounded-[8px] border border-line p-3">
                    <div class="text-sm font-semibold">3 · {{ t('vDns') }}</div>
                    <p class="hint mt-1">{{ t('vDnsHelp') }}</p>
                    <div class="mt-2 flex items-start gap-2">
                        <code dir="ltr" class="min-w-0 flex-1 break-all rounded-[4px] bg-soft p-2 text-start text-xs leading-5">TXT&nbsp;&nbsp;{{ instr.dns.name }}&nbsp;&nbsp;{{ instr.dns.value }}</code>
                        <button type="button" class="icon-btn shrink-0" :aria-label="t('copy')" @click="copyText(instr.dns.value)"><Icon name="copy" :size="16" /></button>
                    </div>
                </div>

                <button type="button" class="btn btn-primary w-full" :disabled="webBusy" @click="checkWebsite">{{ webBusy ? t('vChecking') : t('vCheck') }}</button>
                <p v-if="webMsg" class="text-sm text-amber-800" role="status">{{ webMsg }}</p>
            </div>
        </section>

        <!-- github -->
        <section>
            <h3 class="flex items-center gap-2 text-lg font-bold"><SocialIcon name="github" :size="18" /> {{ t('vGithub') }}</h3>
            <a v-if="gh.oauth && !githubVerified" href="/account/verifications/github/redirect" class="btn btn-primary mt-3 w-full">{{ t('vGhOauth') }}</a>
            <p v-if="gh.oauth && !githubVerified" class="hint my-2 text-center">{{ t('vGhOr') }}</p>
            <form class="mt-3 flex flex-col gap-2" @submit.prevent="startGithub">
                <input v-model="ghHandle" dir="ltr" class="field text-start" placeholder="octocat" :aria-label="t('vGhHandle')" autocapitalize="none" spellcheck="false">
                <button class="btn" :class="gh.oauth ? 'btn-ghost' : 'btn-primary'" :disabled="ghBusy || !ghHandle || githubVerified">{{ githubVerified ? t('vDone') : t('vGhStart') }}</button>
            </form>
            <p v-if="ghErr" class="mt-2 text-sm text-red-600" role="alert">{{ ghErr }}</p>
            <div v-if="ghInstr" class="mt-4 space-y-3">
                <p class="hint">{{ t('vGhGistHelp') }}</p>
                <div class="rounded-[8px] border border-line p-3">
                    <div class="text-sm font-semibold">{{ t('vGhFilename') }}</div>
                    <div class="mt-2 flex items-start gap-2">
                        <code dir="ltr" class="min-w-0 flex-1 break-all rounded-[4px] bg-soft p-2 text-start text-xs leading-5">{{ ghInstr.filename }}</code>
                        <button type="button" class="icon-btn shrink-0" :aria-label="t('copy')" @click="copyText(ghInstr.filename)"><Icon name="copy" :size="16" /></button>
                    </div>
                    <div class="mt-3 text-sm font-semibold">{{ t('vGhContent') }}</div>
                    <div class="mt-2 flex items-start gap-2">
                        <code dir="ltr" class="min-w-0 flex-1 break-all rounded-[4px] bg-soft p-2 text-start text-xs leading-5">{{ ghInstr.content }}</code>
                        <button type="button" class="icon-btn shrink-0" :aria-label="t('copy')" @click="copyText(ghInstr.content)"><Icon name="copy" :size="16" /></button>
                    </div>
                </div>
                <a :href="ghInstr.gist_url" target="_blank" rel="noopener" class="btn btn-ghost w-full">{{ t('vGhOpenGist') }} <Icon name="external" :size="16" /></a>
                <button type="button" class="btn btn-primary w-full" :disabled="ghBusy" @click="checkGithub">{{ ghBusy ? t('vChecking') : t('vCheck') }}</button>
                <p v-if="ghMsg" class="text-sm text-amber-800" role="status">{{ ghMsg }}</p>
                <p class="hint">{{ t('vGhRemoveGist') }}</p>
            </div>
        </section>

        <!-- linkedin -->
        <section v-if="li.enabled">
            <h3 class="flex items-center gap-2 text-lg font-bold"><SocialIcon name="linkedin" :size="18" /> {{ t('vLinkedin') }}</h3>
            <p class="hint mt-1">{{ t('vLiText') }}</p>
            <a v-if="!linkedinVerified" href="/account/verifications/linkedin/redirect" class="btn btn-primary mt-3 w-full">{{ t('vLiBtn') }}</a>
            <p v-else class="mt-3 text-sm font-semibold text-emerald-800">{{ t('vDone') }}</p>
        </section>

        <!-- telegram -->
        <section>
            <h3 class="flex items-center gap-2 text-lg font-bold"><SocialIcon name="telegram" :size="18" /> {{ t('vTelegram') }}</h3>
            <p v-if="!tg.enabled" class="mt-3 rounded-[8px] bg-soft p-3 text-sm text-muted">{{ t('vTgOff') }}</p>
            <template v-else>
                <form class="mt-3 flex flex-col gap-2" @submit.prevent="startTelegram">
                    <input v-model="handle" dir="ltr" class="field text-start" placeholder="@username" :aria-label="t('vHandle')" autocapitalize="none" spellcheck="false">
                    <button class="btn btn-primary" :disabled="tgBusy || !handle || telegramVerified">{{ telegramVerified ? t('vDone') : t('vTelegramBtn') }}</button>
                </form>
                <p v-if="tgErr" class="mt-2 text-sm text-red-600" role="alert">{{ tgErr }}</p>
                <div v-if="tgLink" class="mt-4 rounded-[8px] border border-line p-3" role="status">
                    <a :href="tgLink" target="_blank" rel="noopener" class="btn btn-ghost w-full">{{ t('vOpenTelegram') }} <Icon name="external" :size="16" /></a>
                    <p class="hint mt-2 animate-pulse">{{ t('vWaiting') }}</p>
                </div>
            </template>
        </section>
    </div>
</template>
