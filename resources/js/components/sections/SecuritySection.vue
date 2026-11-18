<script setup>
import { reactive, ref } from 'vue';
import { api, ApiError } from '../../api';
import { state, toast } from '../../store';
import { t } from '../../i18n';
import SaveBar from '../SaveBar.vue';
import ConfirmDialog from '../admin/ConfirmDialog.vue';

const form = reactive({ current_password: '', password: '', password_confirmation: '' });
const errors = ref({});
const busy = ref(false);

async function save() {
    busy.value = true; errors.value = {};
    try {
        await api('PATCH', '/account/password', form);
        Object.assign(form, { current_password: '', password: '', password_confirmation: '' });
        toast(t('passwordChanged'));
    } catch (e) {
        if (!(e instanceof ApiError) || e.status !== 422) throw e;
        errors.value = e.errors;
    }
    busy.value = false;
}
const err = (k) => errors.value[k]?.[0];

const del = reactive({ open: false, busy: false, error: '' });
async function removeAccount({ input }) {
    del.busy = true; del.error = '';
    try {
        await api('DELETE', '/account', { password: input });
        location.href = '/';
    } catch (e) {
        if (!(e instanceof ApiError)) throw e;
        del.error = Object.values(e.errors)[0]?.[0] || e.message; del.busy = false;
    }
}
</script>

<template>
    <div>
    <form class="space-y-4" novalidate @submit.prevent="save">
        <p v-if="state.account.recent_code_login" class="hint">{{ t('recentCodeNote') }}</p>
        <div>
            <label class="label" for="s-cur">{{ t('currentPassword') }}</label>
            <input id="s-cur" v-model="form.current_password" type="password" dir="ltr" class="field text-start" autocomplete="current-password">
            <p v-if="err('current_password')" class="mt-1 text-sm text-red-600">{{ err('current_password') }}</p>
        </div>
        <div>
            <label class="label" for="s-new">{{ t('newPassword') }}</label>
            <input id="s-new" v-model="form.password" type="password" dir="ltr" class="field text-start" autocomplete="new-password">
            <p v-if="err('password')" class="mt-1 text-sm text-red-600">{{ err('password') }}</p>
            <p v-else class="hint mt-1">{{ t('passwordHint') }}</p>
        </div>
        <div>
            <label class="label" for="s-conf">{{ t('confirmPassword') }}</label>
            <input id="s-conf" v-model="form.password_confirmation" type="password" dir="ltr" class="field text-start" autocomplete="new-password">
        </div>
        <SaveBar :busy="busy" />
    </form>

    <section v-if="!state.account.is_admin" class="mt-10 rounded-[8px] border border-red-200 p-4">
        <h3 class="font-bold text-red-700">{{ t('dangerZone') }}</h3>
        <p class="hint mt-1">{{ t('delAccountText') }}</p>
        <button type="button" class="btn mt-3 w-full !bg-red-600 text-white hover:!bg-red-700" @click="Object.assign(del, { open: true, busy: false, error: '' })">{{ t('delAccount') }}</button>
    </section>
    <ConfirmDialog :open="del.open" :title="t('delAccount')" :body="t('delAccountText')" :confirm-label="t('delAccountBtn')" danger :busy="del.busy" :error="del.error"
                   :input-label="t('delAccountPass')" input-type="password" @close="del.open = false" @confirm="removeAccount" />
    </div>
</template>
