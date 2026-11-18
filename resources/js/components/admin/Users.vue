<script setup>
import { watch } from 'vue';
import { useList, fmtDate, ago, fmtNum } from '../../admin/lib';
import { route, go, hint } from '../../admin/store';
import { t } from '../../i18n';
import Icon from '../Icon.vue';
import Badge from './Badge.vue';
import Pager from './Pager.vue';
import UserAvatar from './UserAvatar.vue';
import UserDrawer from './UserDrawer.vue';

const list = useList('/users', { filter: hint.value?.filter || 'all', sort: 'newest' });
hint.value = null;
const FILTERS = ['all', 'admins', 'suspended', 'unverified', 'with_sites'];
const open = (id) => go('users', null, id);
watch(() => route.page, () => {});
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative min-w-[16rem] flex-1">
                <Icon name="search" :size="18" class="pointer-events-none absolute inset-y-0 start-3 my-auto text-muted" />
                <input v-model="list.params.q" type="search" class="field !ps-10" :placeholder="t('a_search_users')" :aria-label="t('a_search_users')">
            </div>
            <select v-model="list.params.sort" class="field !w-auto" :aria-label="t('a_sort_newest')">
                <option value="newest">{{ t('a_sort_newest') }}</option><option value="oldest">{{ t('a_sort_oldest') }}</option><option value="last_login">{{ t('a_sort_last_login') }}</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-1.5" role="group">
            <button v-for="f in FILTERS" :key="f" type="button" class="rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors"
                    :class="list.params.filter === f ? 'bg-ink text-white' : 'bg-soft text-ink-2 hover:bg-line/60'" :aria-pressed="list.params.filter === f" @click="list.params.filter = f; list.params.page = 1">
                {{ t('a_filter_' + f) }}
            </button>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead class="bg-soft text-start text-xs uppercase tracking-wide text-muted">
                        <tr><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_user') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_status') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_joined') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_last_login') }}</th><th class="px-4 py-2.5 text-start font-semibold">{{ t('a_sites') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="u in list.rows" :key="u.id" class="cursor-pointer hover:bg-soft" tabindex="0" :class="route.id === u.id ? 'bg-brand/5' : ''" @click="open(u.id)" @keydown.enter="open(u.id)">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <UserAvatar :user="u" />
                                    <div class="min-w-0"><div class="truncate font-medium">{{ u.display }}</div><div dir="ltr" class="truncate text-start text-xs text-muted">{{ u.email }}</div></div>
                                </div>
                            </td>
                            <td class="px-4 py-2.5"><div class="flex flex-wrap gap-1"><Badge v-if="u.suspended" tone="red">{{ t('a_suspended') }}</Badge><Badge v-else tone="green">{{ t('a_active') }}</Badge><Badge v-if="u.is_admin" tone="violet">{{ t('a_admin') }}</Badge></div></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-muted">{{ fmtDate(u.created_at) }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-muted">{{ u.last_login_at ? ago(u.last_login_at) : '—' }}</td>
                            <td class="px-4 py-2.5 tabular-nums text-muted">{{ fmtNum(u.sites) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="list.loading && !list.rows.length" class="px-4 py-10 text-center text-muted">{{ t('a_loading') }}</p>
            <div v-else-if="list.error" class="px-4 py-10 text-center"><p class="text-red-700">{{ list.error }}</p><button type="button" class="btn btn-ghost mt-3" @click="list.load">{{ t('a_retry') }}</button></div>
            <p v-else-if="!list.rows.length" class="px-4 py-10 text-center text-muted">{{ t('a_empty') }}</p>
            <div class="border-t border-line"><Pager :meta="list.meta" @page="(p) => (list.params.page = p)" /></div>
        </div>

        <UserDrawer v-if="route.id" :id="route.id" @close="go('users')" @changed="list.load" />
    </div>
</template>
