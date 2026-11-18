---
title: Plans and limits
description: What each plan includes, how payments work, and what changes when a plan ends.
---

Everything essential is **free**: your photo, your public profile, verified accounts, the QR code, statistics and the API for small sites. Paid plans raise the limits and add features for people who run more.

| | Free | Pro | Business |
|---|---|---|---|
| Connected sites (API keys) | 3 | 25 | 200 |
| API requests per minute, per key | 600 | 6,000 | 60,000 |
| Gallery photos | 3 | 12 | 30 |
| Custom sections | 2 | 6 | 12 |
| Webhooks | – | 3 | 20 |
| Custom domain for the profile | – | ✓ | ✓ |
| White-label (no {{brand}} link on the profile) | – | – | ✓ |

The live numbers (and prices) are in the public file [`/pricing.json`]({{origin}}/pricing.json), which is what this site's pricing section reads.

## Paying

Open **Plan & billing** in the dashboard, choose a plan and *Monthly* or *Yearly*, and you are sent to the payment page of the gateway. When you come back, we ask the gateway to **confirm** the payment before activating anything; the amount is always the one on our side, never one sent by the browser. Each payment buys exactly one period, and a receipt is emailed.

- Buying the **same plan again** adds time after the current period ends.
- Buying a **higher plan** starts immediately; while two plans are active the higher one applies. There is no proration.
- Plans do **not** renew by themselves. We email you 7 days before one ends.

## When a plan ends

You go back to Free. **Nothing is deleted.** Things above the Free limits keep working until you change them, but you cannot add more: for example, if you have 10 sites you keep them, and you can add one only after you are back under 3.

## Per-key limits

A key's rate limit comes from its owner's plan. In rare cases (support, abuse) an administrator can set an explicit limit on one key; that overrides the plan, up or down, until it is cleared.

## Self-hosting

Billing is **off** unless you turn it on. Set `BILLING_GATEWAY=zarinpal` with your `ZARINPAL_MERCHANT_ID` (add `ZARINPAL_SANDBOX=true` to test), and change the numbers in `config/plans.php` or the `PLAN_*_PRICE_*` variables. `BILLING_GATEWAY=fake` pretends every payment succeeds so you can try the flow on your computer; it refuses to run in production. With billing off, administrators can still give any plan for any number of days from the admin area.
