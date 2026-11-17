<script setup>
import { lt } from './copy';
import Verified from './Verified.vue';
import SocialIcon from '../SocialIcon.vue';
import Icon from '../Icon.vue';
</script>

<template>
    <div class="card mx-auto w-full max-w-[30rem] !rounded-[4px] px-8 pb-4 pt-8" aria-hidden="true">
        <div class="text-lg font-bold">{{ lt('verHead') }}</div>
        <ul class="mt-3 divide-y divide-line">
            <li class="py-4"><div class="flex items-center gap-2 text-sm font-bold"><Icon name="globe" :size="18" />{{ lt('verWebsite') }}<Verified /></div><div class="mt-0.5 text-base text-brand" dir="ltr">sara.example.ir</div></li>
            <li class="py-4"><div class="flex items-center gap-2 text-sm font-bold"><SocialIcon name="telegram" :size="18" />{{ lt('verTelegram') }}<Verified /></div><div class="mt-0.5 text-base text-brand" dir="ltr">t.me/sara_a</div></li>
        </ul>
    </div>
</template>
