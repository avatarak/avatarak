<script setup>
import { ref, reactive } from 'vue';
import { api, ApiError } from '../api';
import { load } from '../store';
import { t } from '../i18n';

const props = defineProps({ mode: { type: String, default: 'register' }, email: { type: String, default: '' } });
const emit = defineEmits(['close', 'switch']);

const form = reactive({ name: '', email: props.email, password: '', username: '' });
const errors = ref({});
const busy = ref(false);
const message = ref('');

async function submit() {
    busy.value = true; errors.value = {}; message.value = '';
    try {
        await api('POST', props.mode === 'register' ? '/register' : '/login', form);
        await load();
    } catch (e) {
        if (!(e instanceof ApiError)) throw e;
        errors.value = e.errors; message.value = Object.keys(e.errors).length ? '' : e.message;
    } finally {
        busy.value = false;
    }
}
const err = (k) => errors.value[k]?.[0];
</script>

<template>
    <div class="fixed inset-0 z-50 grid place-items-center bg-ink/45 p-4" role="dialog" aria-modal="true"
         @click.self="emit('close')" @keydown.esc="emit('close')">
        <form class="card w-full max-w-sm p-7" @submit.prevent="submit">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-2xl font-bold">{{ t(mode) }}</h2>
                <button type="button" class="icon-btn" :aria-label="t('close')" @click="emit('close')">✕</button>
            </div>

            <div class="space-y-4">
                <template v-if="mode === 'register'">
                    <div>
                        <label class="label" for="a-name">{{ t('name') }}</label>
                        <input id="a-name" v-model="form.name" class="field" autocomplete="name" required>
                        <p v-if="err('name')" class="mt-1 text-sm text-red-600">{{ err('name') }}</p>
                    </div>
                    <div>
                        <label class="label" for="a-user">{{ t('username') }}</label>
                        <input id="a-user" v-model="form.username" dir="ltr" class="field" autocomplete="username" required>
                        <p v-if="err('username')" class="mt-1 text-sm text-red-600">{{ err('username') }}</p>
                    </div>
                </template>
                <div>
                    <label class="label" for="a-email">{{ t('email') }}</label>
                    <input id="a-email" v-model="form.email" type="email" dir="ltr" class="field" autocomplete="email" required>
                    <p v-if="err('email')" class="mt-1 text-sm text-red-600">{{ err('email') }}</p>
                </div>
                <div>
                    <label class="label" for="a-pass">{{ t('password') }}</label>
                    <input id="a-pass" v-model="form.password" type="password" dir="ltr" class="field"
                           :autocomplete="mode === 'register' ? 'new-password' : 'current-password'" required>
                    <p v-if="err('password')" class="mt-1 text-sm text-red-600">{{ err('password') }}</p>
                    <p v-else-if="mode === 'register'" class="hint mt-1">{{ t('passwordHint') }}</p>
                </div>
                <p v-if="message" class="text-sm text-red-600" role="alert">{{ message }}</p>
            </div>

            <button class="btn btn-primary mt-6 w-full !min-h-[46px]" :disabled="busy">{{ t(mode) }}</button>
            <p class="mt-4 text-center text-sm text-muted">
                {{ mode === 'register' ? t('haveAccount') : t('needAccount') }}
                <button type="button" class="font-medium text-brand hover:underline"
                        @click="emit('switch', mode === 'register' ? 'login' : 'register')">
                    {{ mode === 'register' ? t('login') : t('register') }}
                </button>
            </p>
        </form>
    </div>
</template>
