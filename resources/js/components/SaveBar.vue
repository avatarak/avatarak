<script setup>
import { t } from '../i18n';

defineProps({ busy: Boolean, error: String, disabled: Boolean });
</script>

<template>
    <div class="sticky bottom-0 -mx-5 mt-8 flex items-center gap-3 border-t border-line bg-white/95 px-5 py-4 backdrop-blur">
        <button class="btn btn-primary" :disabled="busy || disabled">{{ busy ? t('saving') : t('save') }}</button>
        <span v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</span>
    </div>
</template>
