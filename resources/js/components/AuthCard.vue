<script setup>
import { ref, reactive, watch } from 'vue';
import { api, ApiError } from '../api';
import { load } from '../store';
import { t } from '../i18n';
import Modal from './Modal.vue';
import Icon from './Icon.vue';

const props = defineProps({ open: Boolean, mode: { type: String, default: 'register' }, email: { type: String, default: '' } });
const emit = defineEmits(['close', 'switch']);

const form = reactive({ name: '', email: '', password: '', username: '' });
const errors = ref({});
const busy = ref(false);
const message = ref('');

// start clean every time it opens (keeps a prefilled email from the landing form)
watch(() => props.open, (v) => {
    if (!v) return;
    Object.assign(form, { name: '', email: props.email, password: '', username: '' });
    errors.value = {}; message.value = ''; busy.value = false;
});

async function submit() {
    if (busy.value) return;
    busy.value = true; errors.value = {}; message.value = '';
    try {
        await api('POST', props.mode === 'register' ? '/register' : '/login', form);
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
                <button type="button" class="icon-btn -me-2" :aria-label="t('close')" @click="emit('close')"><Icon name="close" :size="22" /></button>
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
                <div>
                    <label class="label" for="a-email">{{ t('email') }}</label>
                    <input id="a-email" v-model="form.email" type="email" dir="ltr" class="field text-start" autocomplete="email" :data-autofocus="mode === 'login' ? '' : null">
                    <p v-if="err('email')" class="mt-1 text-sm text-red-600">{{ err('email') }}</p>
                </div>
                <div>
                    <label class="label" for="a-pass">{{ t('password') }}</label>
                    <input id="a-pass" v-model="form.password" type="password" dir="ltr" class="field text-start"
                           :autocomplete="mode === 'register' ? 'new-password' : 'current-password'">
                    <p v-if="err('password')" class="mt-1 text-sm text-red-600">{{ err('password') }}</p>
                    <p v-else-if="mode === 'register'" class="hint mt-1">{{ t('passwordHint') }}</p>
                </div>
                <p v-if="message" class="text-sm text-red-600" role="alert">{{ message }}</p>
            </div>

            <button class="btn btn-primary mt-7 w-full !min-h-[48px]" :disabled="busy">{{ busy ? t('saving') : t(mode) }}</button>
            <p class="mt-5 text-center text-sm text-muted">
                {{ mode === 'register' ? t('haveAccount') : t('needAccount') }}
                <button type="button" class="font-semibold text-brand hover:underline" @click="emit('switch', mode === 'register' ? 'login' : 'register')">
                    {{ mode === 'register' ? t('login') : t('register') }}
                </button>
            </p>
        </form>
    </Modal>
</template>
