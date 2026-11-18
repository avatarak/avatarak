<script setup>
import { ref, reactive, watch, onBeforeUnmount } from 'vue';
import { api, ApiError } from '../api';
import { load } from '../store';
import { t } from '../i18n';
import Modal from './Modal.vue';
import Icon from './Icon.vue';

const props = defineProps({ open: Boolean, mode: { type: String, default: 'register' }, email: { type: String, default: '' } });
const emit = defineEmits(['close', 'switch']);

const form = reactive({ name: '', email: '', password: '', username: '', code: '', recovery: '' });
const step = ref('password');   // password | email | code | twofa  (login only)
const useRecovery = ref(false);
const wait = ref(0);
let timer;
const errors = ref({});
const busy = ref(false);
const message = ref('');

// start clean every time it opens (keeps a prefilled email from the landing form)
watch(() => props.open, (v) => {
    if (!v) return;
    Object.assign(form, { name: '', email: props.email, password: '', username: '', code: '', recovery: '' });
    errors.value = {}; message.value = ''; busy.value = false; step.value = 'password';
});

function startWait() {
    wait.value = 60; clearInterval(timer);
    timer = setInterval(() => { if (--wait.value <= 0) clearInterval(timer); }, 1000);
}
onBeforeUnmount(() => clearInterval(timer));

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
            await load();
        } catch (e) {
            if (!(e instanceof ApiError)) throw e;
            errors.value = e.errors; message.value = Object.keys(e.errors).length ? '' : e.message;
            busy.value = false;
        }
        return;
    }
    if (props.mode === 'login' && step.value === 'code') {
        busy.value = true; errors.value = {}; message.value = '';
        try {
            const r = await api('POST', '/login/code/verify', { email: form.email, code: form.code });
            if (r?.two_factor) { step.value = 'twofa'; form.code = ''; busy.value = false; return; }
            await load();
        } catch (e) {
            if (!(e instanceof ApiError)) throw e;
            errors.value = e.errors; message.value = Object.keys(e.errors).length ? '' : e.message;
            busy.value = false;
        }
        return;
    }
    busy.value = true; errors.value = {}; message.value = '';
    try {
        const r = await api('POST', props.mode === 'register' ? '/register' : '/login', form);
        if (r?.two_factor) { step.value = 'twofa'; form.code = ''; busy.value = false; return; }
        await load();
    } catch (e) {
        if (!(e instanceof ApiError)) throw e;
        errors.value = e.errors; message.value = Object.keys(e.errors).length ? '' : e.message;
        busy.value = false;
    }
}
const err = (k) => errors.value[k]?.[0];
</script>

<template>
    <Modal :open="open" :label="t(mode)" width="26rem" @close="emit('close')">
        <form class="p-7" novalidate @submit.prevent="submit">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-2xl font-bold">{{ t(mode) }}</h2>
                <button type="button" class="icon-btn -me-2" :aria-label="t('close')" @click="emit('close')"><Icon name="close" :size="20" /></button>
            </div>

            <div class="space-y-4">
                <template v-if="mode === 'register'">
                    <div>
                        <label class="label" for="a-name">{{ t('name') }}</label>
                        <input id="a-name" v-model="form.name" class="field" autocomplete="name" data-autofocus>
                        <p v-if="err('name')" class="mt-1 text-sm text-red-600">{{ err('name') }}</p>
                    </div>
                    <div>
                        <label class="label" for="a-user">{{ t('username') }}</label>
                        <input id="a-user" v-model="form.username" dir="ltr" class="field text-start" autocomplete="username" autocapitalize="none" spellcheck="false">
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
                    <input id="a-email" v-model="form.email" type="email" dir="ltr" class="field text-start" autocomplete="email" :readonly="step === 'code'" :data-autofocus="mode === 'login' ? '' : null">
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
                    <input id="a-pass" v-model="form.password" type="password" dir="ltr" class="field text-start"
                           :autocomplete="mode === 'register' ? 'new-password' : 'current-password'">
                    <p v-if="err('password')" class="mt-1 text-sm text-red-600">{{ err('password') }}</p>
                    <p v-else-if="mode === 'register'" class="hint mt-1">{{ t('passwordHint') }}</p>
                </div>
                <p v-if="message" class="text-sm text-red-600" role="alert">{{ message }}</p>
            </div>

            <button class="btn btn-primary mt-7 w-full !min-h-[48px]" :disabled="busy">
                {{ busy ? t('saving') : (mode === 'login' && step === 'email') ? t('sendCode') : (mode === 'login' && step === 'code') ? t('codeEnter') : (mode === 'login' && step === 'twofa') ? t('tfVerify') : t(mode) }}
            </button>
            <p v-if="mode === 'login' && step !== 'twofa'" class="mt-4 text-center text-sm">
                <button v-if="step === 'password'" type="button" class="font-semibold text-brand hover:underline" @click="step = 'email'; errors = {}; message = ''">{{ t('codeLogin') }}</button>
                <button v-else type="button" class="font-semibold text-brand hover:underline" @click="step = 'password'; errors = {}; message = ''">{{ t('passwordLogin') }}</button>
            </p>
            <p class="mt-5 text-center text-sm text-muted">
                {{ mode === 'register' ? t('haveAccount') : t('needAccount') }}
                <button type="button" class="font-semibold text-brand hover:underline" @click="emit('switch', mode === 'register' ? 'login' : 'register')">
                    {{ mode === 'register' ? t('login') : t('register') }}
                </button>
            </p>
        </form>
    </Modal>
</template>
