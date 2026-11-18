<script setup>
import { ref, reactive, onMounted } from 'vue';
import { api, ApiError } from '../../api';
import { state, toast } from '../../store';
import { t } from '../../i18n';
import { copyText } from '../../composables';
import Icon from '../Icon.vue';

const status = reactive({ enabled: false, left: 0, loaded: false });
const phase = ref('idle');             // idle | password | scan | codes | disable | regen
const form = reactive({ password: '', code: '' });
const setup = ref(null);               // {secret, qr}
const codes = ref([]);
const error = ref('');
const busy = ref(false);

async function load() {
    const r = await api('GET', '/account/2fa');
    Object.assign(status, { enabled: r.enabled, left: r.recovery_codes_left, loaded: true });
}
onMounted(load);

const fail = (e) => { error.value = e instanceof ApiError ? (Object.values(e.errors || {})[0]?.[0] || e.message) : t('error'); };
const reset = () => { phase.value = 'idle'; Object.assign(form, { password: '', code: '' }); error.value = ''; setup.value = null; };
const needsPassword = () => !state.account.recent_code_login;

async function startSetup() {
    busy.value = true; error.value = '';
    try { setup.value = await api('POST', '/account/2fa/setup', { password: form.password }); phase.value = 'scan'; form.code = ''; } catch (e) { fail(e); } finally { busy.value = false; }
}
async function confirm() {
    busy.value = true; error.value = '';
    try {
        codes.value = (await api('POST', '/account/2fa/confirm', { code: form.code })).recovery_codes;
        state.account.two_factor = true; phase.value = 'codes'; toast(t('tfEnabledToast')); await load();
    } catch (e) { fail(e); } finally { busy.value = false; }
}
async function disable() {
    busy.value = true; error.value = '';
    try { await api('POST', '/account/2fa/disable', { password: form.password, code: form.code }); state.account.two_factor = false; toast(t('tfDisabledToast')); reset(); await load(); } catch (e) { fail(e); } finally { busy.value = false; }
}
async function regen() {
    busy.value = true; error.value = '';
    try { codes.value = (await api('POST', '/account/2fa/recovery-codes', { password: form.password, code: form.code })).recovery_codes; phase.value = 'codes'; await load(); } catch (e) { fail(e); } finally { busy.value = false; }
}
function download() {
    const blob = new Blob([`${state.account.email}\n\n${codes.value.join('\n')}\n`], { type: 'text/plain' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'recovery-codes.txt'; a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 3000);
}
</script>

<template>
    <section class="rounded-[8px] border border-line p-4">
        <div class="flex items-center justify-between gap-3">
            <h3 class="flex items-center gap-2 font-bold"><Icon name="shield" :size="20" /> {{ t('tfTitle') }}</h3>
            <span v-if="status.loaded" class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="status.enabled ? 'bg-emerald-50 text-emerald-800' : 'bg-soft text-ink-2'">{{ status.enabled ? t('tfOn') : t('tfOff') }}</span>
        </div>
        <p class="hint mt-2">{{ t('tfIntro') }}</p>

        <!-- idle -->
        <template v-if="phase === 'idle' && status.loaded">
            <button v-if="!status.enabled" type="button" class="btn btn-primary mt-3 w-full" @click="phase = needsPassword() ? 'password' : 'scan'; needsPassword() || startSetup()">{{ t('tfEnable') }}</button>
            <template v-else>
                <p class="mt-3 text-sm text-muted">{{ status.left }} {{ t('tfLeft') }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="btn btn-ghost" @click="phase = 'regen'">{{ t('tfRegen') }}</button>
                    <button type="button" class="btn btn-ghost !text-red-700" @click="phase = 'disable'">{{ t('tfDisable') }}</button>
                </div>
            </template>
        </template>

        <!-- password before setup -->
        <form v-else-if="phase === 'password'" class="mt-3 space-y-3" novalidate @submit.prevent="startSetup">
            <label class="label" for="tf-pass">{{ t('tfPassword') }}</label>
            <input id="tf-pass" v-model="form.password" type="password" dir="ltr" class="field text-start" autocomplete="current-password" data-autofocus>
            <p v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</p>
            <div class="flex gap-2"><button class="btn btn-primary flex-1" :disabled="busy || !form.password">{{ t('tfEnable') }}</button><button type="button" class="btn btn-ghost" @click="reset">{{ t('cancel') }}</button></div>
        </form>

        <!-- scan + confirm -->
        <form v-else-if="phase === 'scan' && setup" class="mt-3 space-y-3" novalidate @submit.prevent="confirm">
            <p class="text-sm">{{ t('tfScan') }}</p>
            <div class="mx-auto w-fit rounded-[8px] border border-line bg-white p-2" v-html="setup.qr.replace(/<\?xml[^>]*\?>/, '')"></div>
            <div class="flex items-center justify-between gap-2 rounded-[6px] bg-soft px-3 py-2">
                <code dir="ltr" class="select-all break-all text-sm tracking-wide">{{ setup.secret }}</code>
                <button type="button" class="icon-btn" :aria-label="t('copy')" @click="copyText(setup.secret.replace(/ /g, ''))"><Icon name="copy" :size="16" /></button>
            </div>
            <label class="label" for="tf-code">{{ t('tfCodeLabel') }}</label>
            <input id="tf-code" v-model="form.code" dir="ltr" inputmode="numeric" maxlength="12" class="field text-center text-xl tracking-[0.4em]" autocomplete="one-time-code" data-autofocus>
            <p v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</p>
            <div class="flex gap-2"><button class="btn btn-primary flex-1" :disabled="busy || form.code.length < 6">{{ t('tfConfirm') }}</button><button type="button" class="btn btn-ghost" @click="reset">{{ t('cancel') }}</button></div>
        </form>

        <!-- recovery codes (shown once) -->
        <div v-else-if="phase === 'codes'" class="mt-3 space-y-3">
            <h4 class="font-semibold">{{ t('tfCodesTitle') }}</h4>
            <p class="text-sm leading-6 text-amber-900">{{ t('tfCodesText') }}</p>
            <ul dir="ltr" class="grid grid-cols-2 gap-2 rounded-[8px] bg-soft p-3 font-mono text-sm"><li v-for="c in codes" :key="c" class="select-all text-center">{{ c }}</li></ul>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-ghost" @click="copyText(codes.join('\n'))"><Icon name="copy" :size="16" /> {{ t('tfCopyCodes') }}</button>
                <button type="button" class="btn btn-ghost" @click="download"><Icon name="download" :size="16" /> {{ t('tfDownloadCodes') }}</button>
            </div>
            <button type="button" class="btn btn-primary w-full" @click="reset(); codes = []">{{ t('tfSaved') }}</button>
        </div>

        <!-- disable / regenerate: password + a code -->
        <form v-else-if="phase === 'disable' || phase === 'regen'" class="mt-3 space-y-3" novalidate @submit.prevent="phase === 'disable' ? disable() : regen()">
            <p class="text-sm">{{ t('tfDisableText') }}</p>
            <template v-if="needsPassword()">
                <label class="label" for="tf-pass2">{{ t('tfPassword') }}</label>
                <input id="tf-pass2" v-model="form.password" type="password" dir="ltr" class="field text-start" autocomplete="current-password">
            </template>
            <label class="label" for="tf-code2">{{ t('tfCodeOrRecovery') }}</label>
            <input id="tf-code2" v-model="form.code" dir="ltr" maxlength="24" class="field text-center" autocomplete="one-time-code" data-autofocus>
            <p v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</p>
            <div class="flex gap-2">
                <button class="btn flex-1" :class="phase === 'disable' ? '!bg-red-600 text-white hover:!bg-red-700' : 'btn-primary'" :disabled="busy || !form.code">{{ phase === 'disable' ? t('tfDisable') : t('tfRegen') }}</button>
                <button type="button" class="btn btn-ghost" @click="reset">{{ t('cancel') }}</button>
            </div>
        </form>
    </section>
</template>
