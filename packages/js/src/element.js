/**
 * <avatarak-avatar email="ali@example.com" size="48" default="identicon" alt="Ali"></avatarak-avatar>
 *
 * Drop-in avatar for any page. The address is hashed in the browser (it is never sent); the element
 * renders an <img> pointing at the avatar URL, with a 2x image for sharp screens.
 *
 *   <script type="module" src="https://unpkg.com/@avatarak/client/src/element.js"></script>
 */
import { avatarUrl, DEFAULT_BASE_URL } from './index.js';

export class AvatarakAvatar extends HTMLElement {
    static observedAttributes = ['email', 'hash', 'size', 'default', 'base-url', 'alt'];

    connectedCallback() { this.#render(); }
    attributeChangedCallback() { if (this.isConnected) this.#render(); }

    async #render() {
        const who = this.getAttribute('hash') || this.getAttribute('email');
        if (!who) return;
        const size = Math.max(16, Math.min(512, Number(this.getAttribute('size')) || 64));
        const opts = { default: this.getAttribute('default') || undefined, baseUrl: this.getAttribute('base-url') || DEFAULT_BASE_URL };
        const [src, src2x] = await Promise.all([avatarUrl(who, { ...opts, size }), avatarUrl(who, { ...opts, size: Math.min(512, size * 2) })]);

        let img = this.querySelector('img');
        if (!img) { img = document.createElement('img'); this.append(img); }
        img.src = src;
        img.srcset = `${src2x} 2x`;
        img.width = img.height = size;
        img.alt = this.getAttribute('alt') ?? '';
        img.loading = 'lazy';
        img.style.borderRadius = 'inherit';
    }
}

if (!customElements.get('avatarak-avatar')) customElements.define('avatarak-avatar', AvatarakAvatar);
