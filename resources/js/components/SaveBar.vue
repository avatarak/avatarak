<script setup>
defineProps({ busy: Boolean, error: String, disabled: Boolean });
import { t } from '../i18n';
</script>

<template>
    <div class="mt-6 flex items-center gap-3">
        <button class="btn btn-primary" :disabled="busy || disabled">{{ busy ? t('saving') : t('save') }}</button>
        <span v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</span>
    </div>
</template>
