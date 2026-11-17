// Extracts the UI icons we use from @tabler/icons (MIT) into resources/data/ui-icons.json.
// Run: node scripts/build-icons.mjs   (only needed when the icon list changes)
import { readFileSync, writeFileSync } from 'node:fs';

const MAP = {
    user: 'user', link: 'link', mail: 'mail', palette: 'palette', shield: 'shield-check', code: 'code',
    back: 'arrow-left', external: 'external-link', chevron: 'chevron-right', eye: 'eye', eyeOff: 'eye-off',
    grip: 'grip-vertical', plus: 'plus', trash: 'trash', check: 'check', camera: 'camera', rotate: 'rotate-clockwise',
    share: 'share-2', copy: 'copy', logout: 'logout', image: 'photo', close: 'x', settings: 'settings', globe: 'world', pin: 'map-pin', qr: 'qrcode', verified: 'rosette-discount-check', gift: 'gift',
};

const out = {};
for (const [name, file] of Object.entries(MAP)) {
    const svg = readFileSync(`node_modules/@tabler/icons/icons/outline/${file}.svg`, 'utf8');
    const inner = svg.replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '')
        .replace(/<path stroke="none" d="M0 0h24v24H0z" fill="none"\s*\/?>/, '').trim();
    out[name] = inner;
}
writeFileSync('resources/data/ui-icons.json', JSON.stringify(out));
console.log(Object.keys(out).length, 'icons written');
