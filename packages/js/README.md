# @avatarak/client

JavaScript client for the [Avatarak.ir](https://avatarak.ir) avatar and profile API. Zero dependencies; browsers, Node 18+, Deno, Bun.

```bash
npm install @avatarak/client
```

```js
import { Avatarak, avatarUrl } from '@avatarak/client';

await avatarUrl('ali@example.com', { size: 96 });                 // no key, no request
const api = new Avatarak({ apiKey: process.env.AVATARAK_KEY });    // keep the key on the server
await api.profile('ali@example.com');                              // object | null
await api.lookup(['a@x.ir', 'b@y.com']);                           // { hash: object | null }
```

```html
<script type="module" src="https://unpkg.com/@avatarak/client/src/element.js"></script>
<avatarak-avatar email="ali@example.com" size="48"></avatarak-avatar>
```

Full guide: `/docs/sdks` on your Avatarak install. MIT licensed.
