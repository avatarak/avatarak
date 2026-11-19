<script setup>
import { computed } from 'vue';
import { state } from '../../store';
import { t, i18n } from '../../i18n';
import Icon from '../Icon.vue';

defineEmits(['goto']);

// the few things that make a profile worth visiting; each opens the right screen
const steps = computed(() => {
    const p = state.account.profile;
    const verified = state.account.emails.some((e) => e.verified);
    return [
        ['avatars', !!p.has_avatar],
        ['about', !!((p.name_fa || p.name_en) && (p.bio || p.headline || p.bio_alt || p.headline_alt))],
        ['links', !!((p.links || []).length || Object.values(p.socials || {}).some(Boolean))],
        ['emails', verified],
    ];
});
const nf = computed(() => new Intl.NumberFormat(i18n.locale === 'fa' ? 'fa-IR' : 'en-US'));
const done = computed(() => steps.value.filter(([, ok]) => ok).length);
</script>

<template>
    <section v-if="done < steps.length" class="mt-4 rounded-[10px] border border-line bg-white p-4 shadow-card" :aria-label="t('gsTitle')">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-bold">{{ t('gsTitle') }}</h2>
            <span class="text-sm text-muted" dir="auto">{{ t('gsCount', { done: nf.format(done), total: nf.format(steps.length) }) }}</span>
        </div>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-soft" role="progressbar" :aria-valuenow="done" aria-valuemin="0" :aria-valuemax="steps.length">
            <div class="h-full rounded-full bg-brand transition-all" :style="{ width: (done / steps.length) * 100 + '%' }"></div>
        </div>
        <ul class="mt-2">
            <li v-for="[id, ok] in steps" :key="id">
                <button type="button" class="flex min-h-[48px] w-full items-center gap-3 rounded-[6px] px-1 text-start" :class="ok ? 'text-muted' : 'font-medium'" @click="$emit('goto', id)">
                    <span class="grid size-6 shrink-0 place-items-center rounded-full border" :class="ok ? 'border-brand bg-brand text-white' : 'border-line'">
                        <Icon v-if="ok" name="check" :size="14" />
                    </span>
                    <span class="flex-1" :class="ok ? 'line-through' : ''">{{ t('gs_' + id) }}</span>
                    <Icon v-if="!ok" name="chevron" :size="16" flip class="text-muted" />
                </button>
            </li>
        </ul>
    </section>
</template>
