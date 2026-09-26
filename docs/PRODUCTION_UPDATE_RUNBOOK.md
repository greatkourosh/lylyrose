# Production update runbook — `lylyrose.ir`

**Status: BLOCKED on step 1. Nothing on this page has been run yet.**

The 23 pending updates and the failed auto-update are still outstanding. This page is
the procedure to unblock and finish them, in order. Every step names how to verify it,
because the thing that went wrong before was a step that "succeeded" silently.

Last verified live: 2026-09-26. Production core **7.1**, WooCommerce 11.1.0,
Dokan 5.1.1, woo-wallet 1.6.14, redis-cache 2.8.0 (now deactivated).
Site health at that moment: `/` `/shop/` `/cart/` `/checkout/` `/my-account/` all 200.

---

## Step 1 — Connect a backup destination (blocks everything else)

**Why this is first and not optional:** UpdraftPlus is active on production, which
makes it look like backups are covered. They are not.

- `updraft_interval` = **manual** — no scheduled backup has ever run.
- Backup history is **empty**.
- The only configured destination, **UpdraftVault**, has `email: ""` and an unknown
  quota. It is configured but **never connected**.

So production has **no backup, local or remote**, right now. Running 23 updates
against that is not a reasonable risk on a store taking payments.

### Do this

1. WP admin → **UpdraftPlus** → *Settings* → *Backup destinations*.
2. Connect one:
   - **UpdraftVault** (built in — create a free account; it gives off-site storage)
   - or Google Drive / Dropbox / S3 / FTP if you already have one.
3. Under *Backup schedule*, set the intervals to something automatic
   (**daily** is reasonable). Manual is how this got missed.

### Verify — do not skip

Back in UpdraftPlus, run **Back up now** and confirm the log ends in success, then
confirm **Existing backups** shows a real file with a size and a date. An entry that
appears but is 0 bytes, or an error the UI scrolls past, is not a backup.

### Then also take a local one

A second backup with the destination unset writes to `wp-content/updraft/` on the
same host. Cheap, and it gives you a rollback point for the update pass itself — but
it does **not** survive the host being lost. Do the off-site one first; treat the
local one as a convenience, not a safety net.

---

## Step 2 — Fix the recorded failed auto-update

The dashboard carries a notice that an automatic WordPress update did not complete.
Clear that **before** the bulk pass, or the same partial state may repeat and you
will not know which half of the update it left behind.

Look at *Dashboard → Updates*, open the failure message, and use its **Retry** link
once. If it fails again, stop and read the error — do not proceed to step 3. A
repeated failure is a real fault, not noise to click past.

---

## Step 3 — Update core first, alone

`Updates → WordPress → Update Now`, by itself. Core before plugins, because
WooCommerce and Dokan both declare a minimum core version and a half-updated core
with updated plugins is the worst of both worlds.

**Verify:** `wp-admin` loads, the dashboard is not in maintenance mode, and
`/` `/shop/` `/cart/` `/checkout/` all still return **200**. Check the site from a
logged-out request — an admin page returning 200 proves nothing about a visitor.

---

## Step 4 — Update plugins, in batches, with a checkpoint between each

Not all 20 at once. WordPress's bulk updater has run and left a site in recovery mode
before, and a batch that takes the site down leaves you guessing which plugin did it.

Suggested batches:

1. **Low-risk / cosmetic first** — Clarity, Google Site Kit, autoptimize, wishlist.
   If a batch breaks the storefront you want to know it wasn't Dokan or WooCommerce.
2. **WooCommerce and its Persian stack** — `woocommerce`, `persian-woocommerce`,
   `persian-woocommerce-sms`, `gateland`, `persian-woocommerce-shipping`,
   `ti-woocommerce-wishlist`. These are coupled; update them together.
3. **Dokan alone** — `dokan-lite`. It is the one with a version history here
   (see `docs/CONTINUATION.md`) and the most likely to be load-bearing.
4. **The rest** — ZarinPal gateway, wallet, updraftplus, wp-mail-smtp, parsidate,
   cart-abandonment-recovery, rank-math, wordfence, limit-login-attempts,
   products-extractor, redis-cache, hello, akismet.

**Verify after every batch:** the four page checks above, plus a real cart → checkout
round trip. A storefront that renders but cannot check out is a failed update, and
the home page returning 200 will not tell you that.

After the whole pass, confirm the pending count actually reached zero — an
"update all" that appears to run and leaves items behind is a known failure mode:

```
Updates → should show no pending updates for core, plugins and themes
```

---

## Step 5 — Reconcile the docs

Local and production versions are still different after this, until the deploy is
actually run. Update the version lines in `docs/CONTINUATION.md` and
`DEPLOYMENT_SUMMARY.md` **from the live site**, not from local — that mistake is
documented in the audit section at the top of `CONTINUATION.md`, and the reason those
docs were wrong in the first place is that they were written from local state.

---

## What is deliberately still open after all this

- **ZarinPal live merchant code** and **`sandbox: no`** — not yet acquired. Until
  then production runs the gateway in sandbox and takes no real payments.
- **PWSMS real credentials** — the `Logger` sink makes production SMS a **silent
  no-op**, which also means the P0 mobile OTP login feature does not work on the live
  site. It is worth treating this as higher priority than it looks.
- **WP Mail SMTP credentials** — mail is configured but unverified on real delivery.
- **WP Super Cache** re-configure and **Wordfence** scan + firewall mode.
- **`WP_REDIS_*` defines** still in production `wp-config.php`. Inert now that the
  plugin is deactivated; drop them when convenient.
