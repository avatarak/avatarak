<script setup>
import { computed } from 'vue';
import InitialsAvatar from '../InitialsAvatar.vue';

const props = defineProps({ user: { type: Object, required: true }, size: { type: Number, default: 36 } });
const useImg = computed(() => props.user.has_avatar && !props.user.suspended && props.user.username);
</script>

<template>
    <span class="inline-block shrink-0 overflow-hidden rounded-full bg-line align-middle" :style="{ width: size + 'px', height: size + 'px' }">
        <img v-if="useImg" :src="`/${user.username}/avatar?s=${size * 2}&v=${user.avatar_version || ''}`" alt="" class="size-full object-cover" loading="lazy">
        <InitialsAvatar v-else :name="user.display || user.name || ''" :seed="user.username || String(user.id)" />
    </span>
</template>
