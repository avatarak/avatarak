<script setup>
import { ref, computed } from 'vue';
import Icon from '../Icon.vue';
import { lt } from './copy';

defineEmits(['cta']);
const origin = window.location.origin;
const tab = ref('js');

const SAMPLES = {
    js: { name: 'example.js', code: `// 1) Hash the email: SHA-256 of the trimmed, lower-cased address
async function avatarURL(email) {
  const bytes = new TextEncoder().encode(email.trim().toLowerCase());
  const digest = await crypto.subtle.digest('SHA-256', bytes);
  const hash = [...new Uint8Array(digest)]
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');

  // 2) Grab the actual image URL
  return \`${origin}/avatar/\${hash}?s=200\`;
}` },
    php: { name: 'example.php', code: `<?php
// 1) Hash the email: SHA-256 of the trimmed, lower-cased address
$hash = hash('sha256', strtolower(trim($email)));

// 2) Grab the actual image URL
$url = "${origin}/avatar/{$hash}?s=200";` },
    curl: { name: 'profile.sh', code: `# Public profile data for an email hash (needs a free API key)
curl -H "Authorization: Bearer YOUR_API_KEY" \\
  ${origin}/api/v1/profiles/<sha256-of-email>` },
};

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
// tiny highlighter: comments, strings, keywords (the sample code is static, but it is escaped anyway)
const TOKEN = /(\/\/.*|#.*)|('[^'\n]*'|"[^"\n]*"|`[^`\n]*`)|\b(const|async|function|await|return|new|echo)\b/g;
function highlight(code) {
    let out = '', last = 0, m;
    while ((m = TOKEN.exec(code))) {
        out += esc(code.slice(last, m.index));
        const cls = m[1] ? 'text-[#7f8a95]' : m[2] ? 'text-[#e6db74]' : 'text-[#82aaff]';
        out += `<span class="${cls}">${esc(m[0])}</span>`;
        last = m.index + m[0].length;
    }
    return out + esc(code.slice(last));
}
const lines = computed(() => SAMPLES[tab.value].code.split('\n').map(highlight));
</script>

<template>
    <section id="developers" class="scroll-mt-20 bg-black py-16 text-white sm:py-24 lg:py-[120px]">
        <div class="mx-auto grid max-w-[1060px] items-center gap-12 px-5 lg:grid-cols-[minmax(0,520px)_1fr] lg:gap-[70px]">
            <div class="order-2 overflow-hidden rounded-[4px] bg-[#2b343b] lg:order-1" dir="ltr">
                <div class="flex overflow-x-auto bg-[#1f262b] text-sm" role="tablist">
                    <button v-for="(s, k) in SAMPLES" :key="k" type="button" role="tab" :aria-selected="tab === k"
                            class="shrink-0 whitespace-nowrap border-t-2 px-4 py-3.5 transition-colors sm:px-7" :class="tab === k ? 'border-brand bg-[#2b343b] text-white' : 'border-transparent text-white/60 hover:text-white'" @click="tab = k">{{ s.name }}</button>
                </div>
                <pre class="overflow-x-auto p-5 text-[13px] leading-[1.7] text-white/90"><code><span v-for="(l, i) in lines" :key="tab + i" class="flex"><span class="w-8 shrink-0 select-none pe-3 text-end text-white/30">{{ i + 1 }}</span><span class="whitespace-pre" v-html="l || '&nbsp;'"></span></span></code></pre>
            </div>
            <div class="order-1 lg:order-2">
                <p class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.06em] text-[#6f95ff]"><Icon name="code" :size="18" /> {{ lt('devEyebrow') }}</p>
                <h2 class="mt-3 text-balance text-[2.5rem] font-bold leading-[1.15] tracking-tight sm:text-[3.5rem]">{{ lt('devTitle') }}</h2>
                <p class="mt-6 max-w-[28rem] text-lg leading-[1.55] text-white/90">{{ lt('devText') }}</p>
                <button type="button" class="mt-8 inline-flex min-h-[56px] items-center gap-2 rounded-[4px] border border-white/80 px-6 font-semibold transition-colors hover:bg-white hover:text-black" @click="$emit('cta')">{{ lt('devCta') }} <Icon name="chevron" :size="16" flip /></button>
            </div>
        </div>
    </section>
</template>
