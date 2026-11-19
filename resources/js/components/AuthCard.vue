<script setup>
import { ref, reactive, watch, computed, onBeforeUnmount } from 'vue';
import { api, ApiError } from '../api';
import { load, adopt, siteStatus, loadStatus } from '../store';
import { t } from '../i18n';
import Modal from './Modal.vue';
import Icon from './Icon.vue';
import LogoMark from './LogoMark.vue';
import InitialsAvatar from './InitialsAvatar.vue';

const props = defineProps({ open: Boolean, mode: { type: String, default: 'register' }, email: { type: String, default: '' } });
const emit = defineEmits(['close', 'switch']);

const form = reactive({ name: '', email: '', password: '', username: '', code: '', recovery: '', accept_terms: false });
const step = ref('password');   // password | email | code | twofa  (login only)
const useRecovery = ref(false);
const wait = ref(0);
let timer;
const errors = ref({});
const busy = ref(false);
const message = ref('');
const welcome = ref('');   // set once sign-in worked: the button turns into a check mark for a moment
const shake = ref(false);

// start clean every time it opens (keeps a prefilled email from the landing form)
watch(() => props.open, (v) => {
    if (!v) return;
    Object.assign(form, { name: '', email: props.email, password: '', username: '', code: '', recovery: '', accept_terms: false });
    errors.value = {}; message.value = ''; busy.value = false; welcome.value = ''; step.value = 'password';
});

function startWait() {
    wait.value = 60; clearInterval(timer);
    timer = setInterval(() => { if (--wait.value <= 0) clearInterval(timer); }, 1000);
}
onBeforeUnmount(() => clearInterval(timer));

/** A refused form gives a short shake (never a delay: errors should be instant). */
function fail(e) {
    errors.value = e.errors; message.value = Object.keys(e.errors).length ? '' : e.message;
    busy.value = false;
    shake.value = false;
    requestAnimationFrame(() => { shake.value = true; });
}

/**
 * Signed in. Fetch the account and warm up the dashboard code while the button shows a check mark for a moment,
 * then open the dashboard. Reduced-motion users get the same words with no waiting animation.
 */
async function finish() {
    welcome.value = t('welcome');
    const started = Date.now();
    try {
        const [acc] = await Promise.all([api('GET', '/account'), import('./Panel.vue').catch(() => null)]);
        welcome.value = acc?.name ? t('welcomeName', { name: acc.name.split(' ')[0] }) : t('welcome');
        const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        await new Promise((r) => setTimeout(r, Math.max(0, (reduce ? 250 : 750) - (Date.now() - started))));
        adopt(acc);
    } catch {
        await load();
    }
}

async function sendCode() {
    if (busy.value) return;
    busy.value = true; errors.value = {}; message.value = '';
    try {
        await api('POST', '/login/code', { email: form.email });
        step.value = 'code'; form.code = ''; startWait();
    } catch (e) {
        if (!(e instanceof ApiError)) throw e;
        errors.value = e.errors; message.value = Object.keys(e.errors).length ? '' : e.message;
    }
    busy.value = false;
}

async function submit() {
    if (busy.value) return;
    if (props.mode === 'login' && step.value === 'email') return sendCode();
    if (props.mode === 'login' && step.value === 'twofa') {
        busy.value = true; errors.value = {}; message.value = '';
        try {
            await api('POST', '/login/2fa', useRecovery.value ? { recovery_code: form.recovery } : { code: form.code });
            await finish();
        } catch (e) {
            if (!(e instanceof ApiError)) throw e;
            fail(e);
        }
        return;
    }
    if (props.mode === 'login' && step.value === 'code') {
        busy.value = true; errors.value = {}; message.value = '';
        try {
            const r = await api('POST', '/login/code/verify', { email: form.email, code: form.code });
            if (r?.two_factor) { step.value = 'twofa'; form.code = ''; busy.value = false; return; }
            await finish();
        } catch (e) {
            if (!(e instanceof ApiError)) throw e;
            fail(e);
        }
        return;
    }
    busy.value = true; errors.value = {}; message.value = '';
    try {
        const r = await api('POST', props.mode === 'register' ? '/register' : '/login', form);
        if (r?.two_factor) { step.value = 'twofa'; form.code = ''; busy.value = false; return; }
        await finish();
    } catch (e) {
        if (!(e instanceof ApiError)) throw e;
        fail(e);
    }
}
loadStatus();
const busyLabel = computed(() => (props.mode === 'register' ? t('registerBusy') : step.value === 'email' ? t('saving') : t('loginBusy')));
const host = window.location.host;
const showSwitch = computed(() => step.value !== 'twofa');
const typed = computed(() => props.mode === 'register' && form.name.trim() !== '');
const handle = computed(() => (form.username || '').trim().toLowerCase());
const err = (k) => errors.value[k]?.[0];
</script>

<template>
    <Modal :open="open" :label="t(mode)" width="26rem" @close="emit('close')">
        <form class="relative" :class="{ 'auth-shake': shake }" novalidate @submit.prevent="submit" @animationend="shake = false">
            <button type="button" class="icon-btn absolute end-3 top-3 z-10" :aria-label="t('close')" @click="emit('close')"><Icon name="close" :size="20" /></button>

            <!-- who you are about to be: the brand on sign-in, your own avatar (built as you type) on sign-up -->
            <div class="grid justify-items-center gap-2.5 rounded-t-[inherit] bg-gradient-to-b from-[#eaf0fd] to-white px-7 pb-2 pt-7 text-center">
                <span class="grid size-[72px] place-items-center overflow-hidden rounded-full bg-brand text-white shadow-[0_0_0_4px_#fff,0_0_0_6px_var(--brand),0_8px_20px_rgb(29_79_196/0.3)] transition-transform duration-300" :class="typed ? 'scale-105' : ''">
                    <InitialsAvatar v-if="typed" :name="form.name" :seed="handle || form.name" />
                    <LogoMark v-else :size="40" face="var(--brand)" />
                </span>
                <h2 class="text-lg font-bold leading-6">{{ typed ? form.name : (mode === 'register' ? t('createAccount') : t('welcome')) }}</h2>
                <p v-if="mode === 'register'" dir="ltr" class="-mt-1.5 text-xs text-muted">{{ host }}/{{ handle || 'username' }}</p>
            </div>
            <div v-if="showSwitch" class="mx-7 mt-3 grid grid-cols-2 gap-1 rounded-[12px] bg-soft p-1" role="tablist" :aria-label="t('login') + ' / ' + t('register')">
                <button v-for="m in ['login', 'register']" :key="m" type="button" role="tab" :aria-selected="mode === m"
                        class="min-h-[40px] rounded-[9px] text-sm font-bold transition-all" :class="mode === m ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink'"
                        @click="mode !== m && emit('switch', m)">{{ t(m) }}</button>
            </div>
            <div class="px-7 pb-7 pt-3">
            <p v-if="mode === 'register' && !siteStatus.registration_open" class="mb-4 rounded-[6px] bg-amber-50 p-3 text-sm leading-6 text-amber-900" role="alert">{{ t('regClosed') }}</p>
            <div class="space-y-4">
                <template v-if="mode === 'register'">
                    <div>
                        <label class="label" for="a-name">{{ t('name') }}</label>
                        <span class="relative block"><Icon name="user" :size="18" class="pointer-events-none absolute top-1/2 -translate-y-1/2 text-muted rtl:right-3 ltr:left-3" /><input id="a-name" v-model="form.name" class="field rtl:!pr-10 ltr:!pl-10" autocomplete="name" data-autofocus></span>
                        <p v-if="err('name')" class="mt-1 text-sm text-red-600">{{ err('name') }}</p>
                    </div>
                    <div>
                        <label class="label" for="a-user">{{ t('username') }}</label>
                        <span class="relative block"><Icon name="link" :size="18" class="pointer-events-none absolute top-1/2 -translate-y-1/2 text-muted rtl:right-3 ltr:left-3" /><input id="a-user" v-model="form.username" dir="ltr" class="field text-start rtl:!pr-10 ltr:!pl-10" autocomplete="username" autocapitalize="none" spellcheck="false"></span>
                        <p v-if="err('username')" class="mt-1 text-sm text-red-600">{{ err('username') }}</p>
                    </div>
                </template>
                <div v-if="step === 'twofa'">
                    <p class="text-sm leading-6 text-ink-2">{{ t('tfChallenge') }}</p>
                    <label class="label mt-4" for="a-2fa">{{ useRecovery ? t('tfRecoveryLabel') : t('codeLabel') }}</label>
                    <input v-if="!useRecovery" id="a-2fa" v-model="form.code" dir="ltr" inputmode="numeric" maxlength="12" class="field text-center text-xl tracking-[0.4em]" autocomplete="one-time-code" data-autofocus>
                    <input v-else id="a-2fa" v-model="form.recovery" dir="ltr" maxlength="24" class="field text-center font-mono" autocomplete="off" autocapitalize="none" spellcheck="false" data-autofocus>
                    <p v-if="err('code')" class="mt-1 text-sm text-red-600">{{ err('code') }}</p>
                    <button type="button" class="mt-2 text-sm font-semibold text-brand hover:underline" @click="useRecovery = !useRecovery; errors = {}; message = ''">{{ useRecovery ? t('tfUseApp') : t('tfUseRecovery') }}</button>
                </div>
                <div v-if="step !== 'twofa'">
                    <label class="label" for="a-email">{{ t('email') }}</label>
                    <span class="relative block"><Icon name="mail" :size="18" class="pointer-events-none absolute top-1/2 -translate-y-1/2 text-muted rtl:right-3 ltr:left-3" /><input id="a-email" v-model="form.email" type="email" dir="ltr" class="field text-start rtl:!pr-10 ltr:!pl-10" autocomplete="email" :readonly="step === 'code'" :data-autofocus="mode === 'login' ? '' : null"></span>
                    <p v-if="err('email')" class="mt-1 text-sm text-red-600">{{ err('email') }}</p>
                </div>
                <div v-if="step === 'code'">
                    <label class="label" for="a-code">{{ t('codeLabel') }}</label>
                    <input id="a-code" v-model="form.code" dir="ltr" inputmode="numeric" maxlength="12" class="field text-center text-xl tracking-[0.4em]" autocomplete="one-time-code" data-autofocus>
                    <p v-if="err('code')" class="mt-1 text-sm text-red-600">{{ err('code') }}</p>
                    <p v-else class="hint mt-1">{{ t('codeSent') }}</p>
                    <button v-if="wait <= 0" type="button" class="mt-2 text-sm font-semibold text-brand hover:underline" @click="sendCode">{{ t('codeResend') }}</button>
                    <p v-else class="hint mt-2">{{ t('codeResendIn') }} {{ wait }} {{ t('seconds') }}</p>
                </div>
                <div v-if="step === 'password'">
                    <label class="label" for="a-pass">{{ t('password') }}</label>
                    <span class="relative block"><Icon name="lock" :size="18" class="pointer-events-none absolute top-1/2 -translate-y-1/2 text-muted rtl:right-3 ltr:left-3" /><input id="a-pass" v-model="form.password" type="password" dir="ltr" class="field text-start rtl:!pr-10 ltr:!pl-10"
                           :autocomplete="mode === 'register' ? 'new-password' : 'current-password'"></span>
                    <p v-if="err('password')" class="mt-1 text-sm text-red-600">{{ err('password') }}</p>
                    <p v-else-if="mode === 'register'" class="hint mt-1">{{ t('passwordHint') }}</p>
                </div>
                <div v-if="mode === 'register'">
                    <label class="flex items-start gap-2.5 text-sm leading-6">
                        <input v-model="form.accept_terms" type="checkbox" class="mt-1.5 size-4 shrink-0 accent-[var(--brand)]">
                        <span>{{ t('acceptPre') }} <a href="/terms" target="_blank" rel="noopener" class="font-semibold text-brand hover:underline">{{ t('acceptTerms') }}</a> {{ t('acceptAnd') }} <a href="/privacy" target="_blank" rel="noopener" class="font-semibold text-brand hover:underline">{{ t('acceptPrivacy') }}</a>{{ t('acceptPost') }}</span>
                    </label>
                    <p v-if="err('accept_terms')" class="mt-1 text-sm text-red-600">{{ err('accept_terms') }}</p>
                </div>
                <p v-if="message" class="text-sm text-red-600" role="alert">{{ message }}</p>
            </div>

            <button class="btn btn-primary mt-7 w-full !min-h-[48px] transition-colors duration-300" :class="welcome ? '!bg-emerald-600 !border-emerald-600 !opacity-100' : busy ? '!opacity-90' : ''" :disabled="busy || !!welcome || (mode === 'register' && !siteStatus.registration_open)">
                <span class="inline-flex items-center justify-center gap-2" aria-live="polite">
                    <Icon v-if="welcome" name="check" :size="20" />
                    <span v-else-if="busy" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white motion-reduce:animate-none" aria-hidden="true"></span>
                    {{ welcome || (busy ? busyLabel : (mode === 'login' && step === 'email') ? t('sendCode') : (mode === 'login' && step === 'code') ? t('codeEnter') : (mode === 'login' && step === 'twofa') ? t('tfVerify') : t(mode)) }}
                </span>
            </button>
            <p v-if="mode === 'login' && step !== 'twofa'" class="mt-4 text-center text-sm">
                <button v-if="step === 'password'" type="button" class="font-semibold text-brand hover:underline" @click="step = 'email'; errors = {}; message = ''">{{ t('codeLogin') }}</button>
                <button v-else type="button" class="font-semibold text-brand hover:underline" @click="step = 'password'; errors = {}; message = ''">{{ t('passwordLogin') }}</button>
            </p>
            </div>
        </form>
    </Modal>
</template>
