# Continuation — Current Project State

> Handoff point: read this, then `git log --oneline -10` and `git status` to pick up.

## Open as of 2026-09-28

1. **`aroma_store` (upstream) has not been deployed.** `aroma-store.vegacodex.ir`
   is still v2.3.0 and `/incredible-offers/` 404s there. Its local tree is the
   source of truth and needs the same targeted push Lyly Rose just got. Note the
   order: Lyly Rose was deployed **first** here on purpose — `hosting-ready` is
   vestigial and 1439 files behind, so the *local* aroma tree is what ships.
2. **Per-host settings still open** (needs a human in `/secure-login/`, all
   described in "Known open items" below): ZarinPal real merchant + `sandbox: no`,
   PWSMS real gateway (still the `Logger` sink, so **production SMS is a no-op**
   and the P0 OTP login does not work live), WP Mail SMTP, enable WP Super Cache
   (`WP_CACHE` is false today), activate Wordfence.
3. **1a-ii, a remote UpdraftPlus target** — optional; the daily schedule writes
   host-local only. Keep 1b (off-host pull) current.
4. **P1, WebP attachments keep the wrong mime type in the DB.** The one failing
   local test. `ASC_Images` converts uploads to WebP by extending WP's
   `image_editor_output_format` — which changes the extension WP *writes* — but
   `wp_insert_attachment()` has already stored the **original** type by then.
   So the file on disk is `.webp` while the attachment row still says
   `image/jpeg`/`image/png`. Verified three ways: a fresh sideload in the local
   container gave `db_mime=image/jpeg` with `mime_content_type()` = `image/webp`;
   the 4 gift-card rows (2849–2852) read `image/png` against magic bytes
   `WEBP`; and **production shows the same, 4 `image/png` rows out of the first
   100 media items.** It is cosmetic — every file really is WebP and serves as
   WebP — but the media library filter, the admin type column, REST
   `/wp/v2/media` and attachment search all read that column. Fix is one
   `wp_update_post()` in `ASC_Images::log_conversion()` (it already runs on
   `wp_generate_attachment_metadata` with the attachment id). It is identical
   upstream, so fix `aroma_store` first — but that tree is `www-data`-owned and
   needs `sudo`. Backfill the 4 existing rows at the same time.
5. **The backup gotcha to keep in mind:** the recorded 5-file deploy set for
   v2.4.0 was incomplete. It omitted two theme files, so the Offers page shipped
   serving 200 with no CSS and no JS. **Derive deploy file lists per commit with
   `--name-status`, not from a single file's diff** — see the v2.4.0 section.

## ✅ `lylyrose-core` v2.4.0 is deployed and verified live (2026-09-28)

Production runs 2.4.0, all 18 classes on the host, `/incredible-offers/` 200
with working assets, gift cards 1811–1814 seeded, rewrite flush fired. Full
verification table and the two-missing-files post-mortem in
["`lylyrose-core` v2.4.0 IS deployed"](#-lylyrose-core-v240-is-deployed--verified-live-2026-09-28)
below. Local suite is **250 passed / 1 failed** — the single failure is the
WebP mime-type defect described in the open items, and it is **pre-existing and
also present on production**, not caused by this deploy.

## 🔍 Production audit — 2026-09-27 (supersedes the 2026-09-26 audit below)

**The update pass is DONE. There is nothing left to update.** Verified
2026-09-27 by reading state off `lylyrose.ir` itself — a read-only PHP probe
uploaded to the docroot with a random name, fetched over HTTPS, then deleted
(the same mechanism the 2026-09-26 rebrand used). Cross-checked against the
plugin files' own `Version:` headers pulled over FTP, so this is not the probe
lying.

| | 2026-09-26 audit said | **Live now (2026-09-27)** |
| --- | --- | --- |
| Pending updates | **23** (20 plugins, 3 themes, core 7.1→7.1.2) | **0 plugins, 0 core** — core already on **7.1.2** |
| Dokan | 5.1.1, with 5.1.3 available | **5.1.3** ✅ |
| WooCommerce | 11.1.0 | **11.1.2** |
| TeraWallet | 1.6.14 | **1.7.0** |
| Redis Object Cache | 2.8.0, deactivated | **3.0.0**, still inactive (drop-in never installed, so it is correctly inert) |
| Failed auto-update | recorded on the dashboard | **no longer present** (`update_core_failed` transient is empty) |

**The remaining 3 are themes, and they are irrelevant:** Twenty Twenty-Four,
-Twenty-Three and -Twenty-Two. None is in use — the live theme is `lylyrose`
1.10.0 (and `twentytwentyfive`). Updating WordPress's default block themes
changes nothing visitor-facing. Left alone deliberately.

**Who did it:** plugin files on the host carry mtime **2026-09-27 06:24**, while
`index.php`, `wp-config.php`, `wp-load.php`, `themes/lylyrose/` and
`plugins/lylyrose-core/` are all still **2026-09-24**. So this was a **partial
update of the updatable plugins only**, not a full re-deploy — which is exactly
what WordPress's own updater does. It happened outside this repo's recorded
history (no commit or dev-log entry), so treat the mechanism as
unconfirmed-but-consistent rather than as something this project did.

**Health verified after the fact** (logged out, so a real visitor's view):

| Check | Result |
| --- | --- |
| `/` `/shop/` `/cart/` `/checkout/` `/my-account/` `/secure-login/` | all **200** |
| `/wp-json/` name, `<title>` | `Lyly Rose` — rebrand intact, no "Aromaland" |
| Product page, add-to-cart | works; cart holds **7,950,000 تومان** with qty steppers |
| Checkout | renders with the live cart, billing fields, coupon box, place-order button |

So runbook steps 2–4 are **complete**; step 5 (this doc reconciliation) is what
this audit did. Step 1c (UpdraftPlus daily schedule) was set the same day, later,
by host-side PHP probe — see the 1c section of the runbook.

**1c's mechanism was verified 2026-09-27 13:40 UTC** (read-only docroot probe,
uploaded/fetched/deleted). Both `updraft_backup` and `updraft_backup_database`
exist as **recurring** events (daily, 86400s, next 2026-09-27 20:46:33 UTC), and
the site's cron loopback to `wp-cron.php` returns **200 with an empty body** in
0.03 s. That second check is the load-bearing one on a host with no cPanel
scheduler: `DISABLE_WP_CRON=false` only says WP is *permitted* to self-spawn, so
without it a registered schedule could sit there forever and be
indistinguishable from one that simply has not fired yet. `wp-content/updraft/`
still holds only the 3 guard files and `updraft_backup_history` is `NULL`, as
expected before the first fire. **The first actual backup was unconfirmed at that
point — that was the one item left, and it was time-based, not blocked. It has
since fired; see the 2026-09-28 entry at the end of this section.**

**Re-verified 2026-09-27 ~17:10 UTC, and the schedule is intact.** The events are
still registered for the same fire time, `2026-09-27 20:46:33 UTC`, each
`schedule: daily` / `interval: 86400`, both under args-hash
`40cd750bba9870f18aada2478b24840a`; `updraft_interval` and
`updraft_interval_database` are both `daily`; `DISABLE_WP_CRON` is still `false`;
no archive and no history yet — correct, since at that moment the fire was still
~3h24m away. A check is scheduled for **00:16 Tehran time, 2026-09-28**, just
after the fire.

Worth recording from that probe: **WP's cron option is keyed by timestamp, not
hook name.** `$cron['updraft_backup']` is empty, so a by-name read reports both
events `MISSING` and looks exactly like a schedule that was silently deleted —
it had not. The real shape is `$cron[<unix ts>][<hook>][<args-hash>]`, and each
event's `schedule`/`interval` sit one level deeper again. Reading it wrong cost
two probe rounds and briefly looked like a production regression. Same lesson as
the three probes in the settings audit below: **a probe that reports a problem
is usually the probe that is wrong**, and here it was the *safe* direction to be
wrong in — nothing on the site changed.

**WP-Cron is running normally — confirmed, and the reason the first fire will
work.** A check at ~17:51 UTC found four events overdue, the worst
`action_scheduler_run_queue` (a 60-second event) by 26 minutes, which looks like
a stalled cron. It is not: a single page view — the probe fetch itself —
immediately rescheduled all four (`action_scheduler_run_queue` went from 26 min
overdue to 36 s out). The site is simply **low-traffic**, so between visits the
short-interval events sit past-due until someone arrives. A recurring event that
has been overdue *and rescheduled* is healthy; only one that stays overdue across
page views is broken.

Two consequences worth keeping:

- The fire at 20:46:33 UTC will happen **on the next front-end request after that
  moment**, not at that moment. The scheduled 00:16 check fetches the site, which
  is itself the trigger, so the archive should exist by then.
- Do not read "overdue events" on this site as a fault. Before concluding cron
  is broken, fetch a page and re-read: WP-Cron runs on the *span request* that
  arrives after `doing_cron` is set, so the state right after a page view is the
  honest one.

**Local suite re-verified green 2026-09-27 17:50: `229 passed, 0 failed`**
across 28 sections, on the canonical host `https://lylyrose.local`. The roadmap
in `FEATURES_ROADMAP.md` has no open P0/P1/P2 work — P3 is deliberately optional
— and with 1c now closed (see the next entry) there is no feature work in flight
here.

### ✅ 2026-09-28 04:15 UTC — the first UpdraftPlus backup fired. 1c is closed.

The last open item from the 2026-09-27 audit is **closed with evidence, not
inference**. Six archives appeared in `wp-content/updraft/` sharing run id
`ddea859eb974`, dated `2026-09-28-0415`, totalling **120,790,945 bytes
(115.2 MB)**: `plugins.zip` 85.3 MB, `themes.zip` 18.8 MB, `others.zip` 4.7 MB,
`uploads.zip` 6.1 MB, `db.gz` 407 KB, `mu-plugins.zip` 120 B — plus
`log.ddea859eb974.txt` (108 KB), which did not exist before the fire.

| Check | Result |
| --- | --- |
| `updraft_backup_history` | **no longer `NULL`** — one set, `nonce: ddea859eb974`, `created_by_version: 1.26.8` |
| Run log | 909 lines, **no error/warning/fatal**; ends `The backup succeeded and is now complete` |
| DB archive | gzip decompresses fully (416,686 → 2,673,317 B), **36 tables**, header names `https://lylyrose.ir` and WP 7.1.2 |
| DB checksum | sha1 of downloaded bytes == Updraft's recorded `4879d6a657a69b595a2ee318518db29847da2703` |
| Not stubs | `uploads` 6.35 MB zip vs **1,792 live files / 5.7 MB**; `plugins` 85.3 MB zip vs **20,089 files / 240.9 MB** (ratio 0.35) |
| Site after | `/` `/shop/` `/checkout/` all 200; both probes 404 after deletion |

**Read via FTP** (`nlst` + `SIZE` + `MDTM`, passive) **plus two read-only docroot
probes** — random leading-underscore name, uploaded, fetched over HTTPS,
deleted, each confirmed gone by *both* a 404 and an FTP listing. **Nothing was
triggered by hand**: no "Back up now", no `UpdraftPlus::schedule_backup`, no
`doing_wp_cron` param, no `Backup/fullbackup_to_homedir`.

**Why the note above was right, and how it played out.** The event was stamped
`2026-09-27 20:46:33 UTC`; the archives are stamped `04:15` — **7h29m late**. At
04:15 UTC the cron array still showed `updraft_backup` at its *original* stamp
and still 26,951 s overdue, and `wp-content/updraft/` held only guard files, so
at that instant there was no archive and no evidence of a backup. The docroot
probe that read that state was itself a PHP request, and it is the request that
released the run: by 04:17 the archives were complete. This is precisely the
"a registered schedule is not a backup, but neither is a late one a fault"
case — on a low-traffic store WP-Cron fires on a visitor, not on a clock.

**What is now the backup posture.** The daily schedule writes **host-local only** —
the log records `No remote despatch: user chose no remote backup service`. So the
only automated copy sits on the same host as the site, and 1a-ii (a remote
target) is now the sole remaining gap, still optional. Keep 1b (off-host pull)
current, and keep 1a manual before any risky change.

**Re-checked 2026-09-28 06:55 UTC — the daily cycle actually turned over.** Both
events are still registered as `daily` / `86400` and have moved off the stamp
they fired on: next run `1790628393` = `2026-09-29 04:33:13 UTC`, ~24h after the
04:15 run. That reschedule is the check the archive could not provide — a cron
event looks *identical* before a fire, after a fire, and if it never fires, so
"still registered" alone would have passed even for a schedule that had silently
stopped. `updraft_backup_history` still holds one set; `updraft_retain` and
`updraft_retain_db` are both `2`, so retention caps the directory at **2 sets ≈
231 MB steady-state** on a host of unknown quota — retention is the only thing
stopping this from filling the disk, so don't raise it casually. `/` `/shop/`
`/checkout/` `/my-account/` all 200.

### ✅ `lylyrose-core` v2.4.0 IS deployed — verified live 2026-09-28

**Supersedes the 2026-09-27 "NOT deployed" finding below (kept for history).**
Production now runs **2.4.0** on both the header and `const VERSION`, all 18
`ASC_` classes are on the host, and `/incredible-offers/` serves **200** with
real product cards. Read the "not deployed" section for the original evidence
and the deploy-order investigation — both still valid — but its conclusion is
now stale.

#### The recorded 5-file set was incomplete — 2 files were missing

This is the part worth keeping. The set below was derived from the two v2.4.0
commits, but it was **wrong by two files**, and the mistake was invisible from
the repo side:

| File | In the recorded 5-file set | Why it mattered |
| --- | --- | --- |
| `themes/lylyrose/functions.php` | **no** | held the whole 28-line `lylyrose_flash_sales_assets()` enqueue hook |
| `themes/lylyrose/assets/js/flash-sales.js` | **no** | the count-down timer script itself |

Both are **modifications/additions the commits made to the theme**, and both were
omitted. The four plugin-side files plus two theme files had gone up (mtimes
2026-09-28 11:59 / 12:03), so the deploy *had* run — just incompletely. Result:
the offers page returned **200 while shipping no CSS and no JS at all**. The page
existed and rendered cards, so a status-code check passed. The tell was
`assets/js/flash-sales.js` → **404**.

**Both now uploaded and verified:**

```bash
python3 docker/deploy-targeted.py \
  wp-content/themes/lylyrose/functions.php \
  wp-content/themes/lylyrose/assets/js/flash-sales.js
```

`functions.php` is a PHP syntax boundary — a fatal there takes the whole site
down. The site-wide 200 sweep after upload is what rules that out, since every
front-end request loads it.

**Lesson: derive the file list per commit with `--name-status`, not from the
diff of one file against the other.** Both `d3233c16` and `3ce37382` touch the
theme as well as the plugin, and the theme side is the easy half to miss because
the headline was "the plugin is not deployed".

#### Live verification, 2026-09-28 (read-only, off the site itself)

| Check | Result |
| --- | --- |
| `lylyrose-core.php` on host | header `Version: 2.4.0`, `const VERSION` `2.4.0` — agree |
| `includes/` over FTP | **18** files; `class-flash-sales.php` + `class-gift-cards.php` present, all 18 referenced by the bootstrap |
| 5 original files + the 2 new ones | **byte-identical** to the working tree (sha1) |
| `/incredible-offers/` | **200**, 24 product cards |
| `flash-sales.css` | **200**, 4,204 B, enqueued as `lylyrose-flash-sales-css` |
| `flash-sales.js` | **200**, 1,024 B, enqueued as `lylyrose-flash-sales-js` |
| CSS bundle contents | `.dk-flash-card` `.dk-flash-grid` `.dk-flash-hero` `.dk-flash-tab` `.dk-flash-timer` all present |
| i18n payload | `dk_flash_i18n` present, base64-inline, decodes to `noStock`/`addedToCart` in Persian |
| Auto-created artifacts | page `incredible-offers` id **1810**; gift cards **1811–1814** = `GC-500K`/`GC-1000K`/`GC-2000K`/`GC-5000K`, virtual, correct prices |
| Version-gated rewrite flush | fired — shop links resolve as `/product/sku-13503503/…` and fetch **200** |
| Whole site | `/` `/shop/` `/cart/` `/checkout/` `/my-account/` `/incredible-offers/` `/about/` `/contact/` `/faq/` `/track-order/` all **200** |
| Branding | `<title>Lyly Rose`, `/wp-json/` name `Lyly Rose`, **0** "Aromaland" hits |
| `vegacodex.ir` (primary site) | **200**, untouched |

**Two probes here were wrong, not the site** — the recurring lesson. (1) Grepping
the page for `flash-sales\.(css|js)` found nothing and looked like a dead
enqueue hook; Autoptimize rewrites the tag and the script is footer-loaded, so
the pattern never matched. The real tags are `id='lylyrose-flash-sales-css'` and
`id="lylyrose-flash-sales-js"`. (2) `curl` of `functions.php` over HTTPS returned
**0 bytes** (LiteSpeed/Autoptimize serve that path statically), which looked like
missing code on the host; it was not — FTP showed the file identical with the
hook present twice. **Confirm file identity over FTP, never by fetching PHP over
HTTP.** And `?s=کارت هدیه` on the Store API returns `0` products, which looks
like gift cards failed to seed; they exist (1811–1814), the Store API just does
not match on Persian name. Query by `?sku=GC-500K` instead.

**Still not deployed: `aroma_store` upstream.** `https://aroma-store.vegacodex.ir/`
was 404 for `/incredible-offers/` and was not part of this pass. Its local tree is
the source of truth for the next release and carries both features.

#### The earlier finding, for history

Found 2026-09-27 18:45 UTC, and it is the largest gap between this repo and the
live site. **`/incredible-offers/` returns 404 on `lylyrose.ir`** even though
section 28 of the local suite covers it (12 checks, green) and the port commit
`d3233c16` is in `master`.

The cause is not a bug or a failed deploy — **the code was never uploaded**.
Confirmed three ways:

| Evidence | Result |
| --- | --- |
| `lylyrose-core.php` on the host | header `Version: 2.3.0`, mtime **2026-09-24 08:45** (the deploy date) |
| `wp-content/plugins/lylyrose-core/includes/` over FTP | **16** files, no `class-flash-sales.php` |
| Repo working tree | **17** files incl. `class-flash-sales.php`; header bumped to `2.4.0` |

**Neither site has it.** `https://aroma-store.vegacodex.ir/incredible-offers/`
is also **404** — so upstream has not been deployed either, and this is *not* a
lylyrose-only regression. Per `FEATURE_REQUEST_POLICY.md` the fix therefore
belongs upstream, then mirrored: deploy v2.4.0 to `aroma_store` first, then here.

This is the same shape as the 2026-09-27 partial update noted above: WordPress's
updater refreshed the *updatable* plugins, and everything not in the WordPress
plugin directory — this custom plugin and the theme — stayed at the 2026-09-24
deploy. `CONTINUATION.md` claimed feature parity with upstream, and parity with
the **repo** does hold; parity with the **live site** does not.

**Also note a version-string inconsistency in the repo, inherited from upstream:**
the header says `2.4.0` but `const VERSION` in the same file still says `2.3.0`
(`lylyrose-core.php:7` vs `:19`). That constant is not cosmetic — it defines
`LYLYROSE_CORE_VERSION`, which gates the product-code rewrite flush
(`class-product-code.php:154`) and the store-pages version check
(`class-store-pages.php:45`), so a stale value means those do not re-run.
`aroma_store` has the identical split, so fix it there first.

#### ✅ The mid-mirror blocker is gone (2026-09-28). Suite is 249/0; deploy is unblocked.

The "do not deploy while another session is mid-mirror" warning above is
**resolved**. The gift-card work landed upstream and was mirrored byte-identical
(`diff` clean against `aroma_store`'s `class-gift-cards.php`), upstream is
committed and its own tree is clean, and this repo's suite is **249 passed, 0
failed** (up from 229 — sections 29 and 30 are new). The version split is fixed
on both sides: header and `const VERSION` both read `2.4.0`, and section 30
pins them together so it cannot silently reopen. Mirrored in `3ce37382`.

**Deploy order is NOT upstream-first, and the paragraph above that says so is
wrong.** Investigated 2026-09-28 with read-only git:

- `aroma_store`'s deploy branch is `hosting-ready`, and it is **not merely
  behind `master` — it differs by 1439 files.** It was last touched
  **2026-09-10**, 18 days stale.
- Two independent causes. It is a *stripped* tree by design (no `docker/`,
  `docker-compose.yml`, `.codacy/`), and its **vendor plugins are pinned to
  older versions than production runs** — WooCommerce 11.0.1 there vs **11.1.0
  live**, Dokan 5.0.16 vs 5.1.1. So "merge master → hosting-ready to ship a
  feature" also ships a whole unreviewed plugin update wave.
- **Production was never deployed from `hosting-ready`.** The live site serves
  WooCommerce 11.1.0, which is `master`'s version. There is no CI/CD (the only
  `.circleci` file is vendored inside Rank Math) and no git checkout on the
  host; deploy is a manual FTP/rsync upload from a local working tree.

`hosting-ready` is therefore a staging area nothing deploys from, and it has
drifted. Following its documented "merge then upload" procedure would be a much
larger and riskier change than shipping v2.4.0.

**Use the targeted uploader instead: `docker/deploy-targeted.py`.** It takes an
explicit file list rather than walking a branch, and refuses the vendor plugin
paths by name, so a feature deploy cannot carry a plugin update with it.
`--dry-run` lists what would go up without touching the host.

```bash
python3 docker/deploy-targeted.py --dry-run \
  wp-content/plugins/lylyrose-core/lylyrose-core.php \
  wp-content/plugins/lylyrose-core/includes/class-flash-sales.php \
  wp-content/plugins/lylyrose-core/includes/class-gift-cards.php \
  wp-content/themes/lylyrose/assets/css/flash-sales.css \
  wp-content/themes/lylyrose/page-about.php
```

That is the exact 5-file set (36,221 bytes) pending upload. **Not yet deployed.**

**One deploy-time check worth knowing.** `/incredible-offers/` needs a rewrite
rule, and `ASC_Flash_Sales::ensure_page()` runs at `init:30` while
`ASC_Product_Code::maybe_flush()` runs at `init:10` — so the flush happens
*before* the page exists. That ordering looks like it would leave the route
404-ing, and it was verified rather than assumed: with the page deleted, the
version gate at 2.3.0 and `rewrite_rules` removed, a single plain HTTP request
to `/incredible-offers/` returned **200** with 24 real product cards. WordPress
resolves the page after `init:30` has created it, so the route works. Do not
"fix" that priority.

**Promotion order, for the record:** local aroma → remote aroma → local
lylyrose → remote lylyrose. `aroma_store` is the development/test stage and
`lylyrose` is production. Aroma's local stack is the test environment; it needs
no upload, and its remote promotion should use the same targeted-file approach
rather than a branch merge.



## 🔍 Production audit — 2026-09-26 (superseded by the above, kept for history)

Read this before trusting any "still to do" item in this file. The older sections
were written from the **local** stack's perspective, where the suite is green and
zero updates are pending — that local health was being reported as if it described
production. It does not. Verified directly on `lylyrose.ir` by logging into
`/secure-login/` and reading state via the REST API (read-only except where noted):

| Doc said                                                    | Production actually is                                                                                                                                                                                                                                                        |
| ----------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Dokan 5.0.16, "live Dokan update pending"                   | **5.1.1** already, with **5.1.3** available                                                                                                                                                                                                                                   |
| Suite green at 217/0, port 8080                             | Green at **217/0 re-verified on the new port 8030** (2026-09-26 21:33)                                                                                                                                                                                                        |
| (implied current)                                           | **23 updates pending** — 20 plugins, 3 themes, core 7.1 → 7.1.2                                                                                                                                                                                                               |
| —                                                           | A **failed auto-update is recorded** on the dashboard (automatic WP update did not complete)                                                                                                                                                                                  |
| Redis Object Cache "deactivate + drop `WP_REDIS_*` defines" | Plugin was active but Redis **unreachable** (`Connection refused 127.0.0.1:6379`), drop-in **never installed**, object cache **Not enabled** — so it was never doing anything. **Deactivated 2026-09-26** (19 → 18 active); home/shop/cart/checkout/my-account all still 200. |
| Passwords maybe still `admin/admin123`                      | `admin/admin123` correctly **rejected** ✅                                                                                                                                                                                                                                    |
| —                                                           | **4 administrator accounts**: `admin`, `claude`, `kouroshvega`, `superadmintest` (confirmed intentional 2026-09-26 — keep)                                                                                                                                                    |

**Still open after the audit** (unchanged, needs a decision — do not assume): the
23 pending updates, and the merchant/gateway credentials below. The update pass was
deliberately _not_ run: it was offered as a one-click job, and running 23 updates on
a live store with no verified backup is a different risk class from the config
cleanup that was requested. UpdraftPlus is active but no remote backup target is
configured, so there is currently no restorable backup of production.

This is the third time in this project that a locally-green / docs-current state
turned out to hide a live-site problem. The other two: the `$table_prefix` and
`.htaccess` omissions that looked like a DB import failure, and a `git push` that
reported "Everything up-to-date" on a failed push. **Read state off the live site;
do not infer it from this file.**

### The backup situation — worse than "no remote target"

Chased down the same day, because it is the thing the update pass depends on.
UpdraftPlus is **active**, which makes production look backed up. It is not:

- `updraft_interval` = **manual** — nothing has ever run on a schedule.
- Backup history is **empty** (the history endpoint returns nothing at all).
- The only destination, **UpdraftVault**, has `email: ""` and unknown quota —
  configured but **never connected**.

So production had **no backup, local or remote** — until 2026-09-27, when the
account backup in the runbook was taken and verified. The plugin's presence had
been doing the work of an assurance it could not back up. Do not read
"UpdraftPlus is active" as "production is backed up." The fix was never a paid
subscription: it was scheduling, plus a route that captures the database.

The full unblock-and-run procedure is
**[PRODUCTION_UPDATE_RUNBOOK.md](PRODUCTION_UPDATE_RUNBOOK.md)**. Step 1's backup
is now **taken and verified** (see below); the update pass in steps 2–5 has not
been run.

**1c is DONE (2026-09-27), via host-side PHP probe, not a browser.** The
runbook originally said to set a daily schedule in cPanel → Backup → Configure.
**This host has no such control**: the Backup page offers only *Download a Full
Account Backup*, there is no Backup Wizard (404s), and the `Cron` UAPI module is
not installed (`Can't locate Cpanel/API/Cron.pm`) — so cPanel's "Add New Cron
Job" form is inert too. **No host-side scheduler exists here**; getting one means
asking the host to install it. So 1c was done in UpdraftPlus instead, by
uploading a random `_*.php` probe to the docroot and calling
`update_option('updraft_interval', 'daily')` / `('updraft_interval_database', ...)`
— the same mechanism as the 2026-09-26 rebrand, and admin-equivalent. The
`update_option()` call is load-bearing: those options are `register_setting()`
sanitize callbacks that do the `wp_schedule_event()`. `updraft_backup` and
`updraft_backup_database` are both registered for 2026-09-27 20:46 UTC. The
schedule has not fired yet — confirming the first archive is still open.

**Step 1 was rewritten 2026-09-27 to the free route** (decision: no paid UpdraftVault
subscription). UpdraftPlus's *plugin* is free and scheduling is a free feature — only
*storage* is paid — so there was never a reason to replace the plugin. What was
missing was that nothing was scheduled and nothing was ever taken. The step now
splits the two risks it was conflating:
1a. a backup **on the host**, which is what actually protects the update pass in
steps 2–5. Primary route is now the host's own **cPanel Backup** (free, captures
all account databases + the full site tree), with UpdraftPlus host-local as a
WordPress-level belt-and-braces copy;
1b. pulling that archive to this machine over the **passive FTP** access already
proven by the 2026-09-24 deploy, giving a genuinely off-host copy for free.
Google Drive / Dropbox are free but demoted to optional — the host is in Iran and
those services are restricted from Iranian IPs in practice, so a backup there can
fail silently. Pulled backups land in `backups/` (git-ignored — real customer and
order data).

**Confirmed by FTP listing 2026-09-27:** the FTP login lands at `/`
(= `/home3/bqwyvowk`). `lylyroseir/wp-content/updraft` holds **only `.htaccess`,
`web.config` and `index.html`** — Updraft's empty-folder guard files, and **no
backup archive whatsoever**, which is why Updraft's own history was empty.

**Backup now exists, and has been pulled off-host (2026-09-27).**
On the host: `~/backup-9.27.2026_09-37-51_bqwyvowk.tar.gz` — 389.6 MB, size
stable across a 30s re-check so the job finished. Locally:
`backups/production-2026-09-27/` (same filename), local size equal to the host's
FTP `SIZE` exactly, gzip stream decompresses fully, sha256
`a8c2a1173aaa185ea4913d39fd9cf90b442d00c178926bb0ef8f720b1f726e3f`.

**The site files are under `homedir/lylyroseir/`, not `httpfiles/`.** The archive
does contain an `httpfiles/` entry but it is **empty**, so a verification that
merely greps for `httpfiles/` passes on an archive containing no site files at
all — an earlier draft of this note made exactly that mistake. Assert on
`homedir/lylyroseir/wp-config.php` instead; the current archive has it, plus
1,794 upload files and all three account databases.

Both a files-only and a db-only archive pass a size check and are still useless
here, because steps 3–4 change the schema — so asserting the *database* entry
alongside a *site file* is the check that matters.

Still outstanding: 1a-ii (UpdraftPlus host-local, optional). **The first 1c backup
is no longer outstanding — it fired 2026-09-28 04:15 UTC and was verified (see
the 2026-09-28 entry at the top of this file).** Its mechanism was verified
2026-09-27; the first fire was stamped 2026-09-27 20:46 UTC and ran 7h29m later,
deferred to the first request on a low-traffic site.

## ⚠️ Read first — this project is DOWNSTREAM of `aroma_store`

**`aroma_store` is the source of truth. This project (`lylyrose`) is downstream of it.**

Every feature and fix is developed and tested in `aroma_store` **first**, and mirrored
here only when `lylyrose` needs it. Never the reverse.

**A feature requested in this repo is not a task to implement here — it is a request to
route upstream.** Refer it to `aroma_store`, build and test it there, and mirror it back
only once that suite is green. The step-by-step procedure, the decision flow, and the
store-specific exceptions are in
**[FEATURE_REQUEST_POLICY.md](FEATURE_REQUEST_POLICY.md)** — read it before starting any
feature work.

The full rule set, the rename map, the current upstream/downstream divergence, and the
mirror procedure live in **[UPSTREAM_RELATIONSHIP.md](UPSTREAM_RELATIONSHIP.md)**.

`aroma_store` was ahead by one feature (`ASC_Flash_Sales` / `/incredible-offers/`)
until 2026-09-27, when the port landed here. **The two are now at feature parity** —
17 `ASC_` classes each, and suite section 28 covers the offers page.

Note the "Inherited Aroma Store history" section below is historical shorthand, not a
description of the relationship: those entries were copied into this file when the two
projects shared a codebase. Upstream is the parent, not the ancestor-of-record.

## Verified Lyly Rose state — 2026-09-26

- **LIVE**: `https://lylyrose.ir` is deployed and serving. Files uploaded (25,844 files / 342 MB, 0 failures), database `bqwyvowk_lylyrose` created and imported (1,218 statements, 97 tables, 0 failures), `wp-config.php` with real credentials + fresh salts, and `.htaccess` carries the WordPress rewrite block. Verified: home, `/shop/`, `/cart/`, `/checkout/`, `/my-account/`, `/secure-login/` all 200; add-to-cart works (WooCommerce fragment confirms 1 item @ 7,800,000 Toman); product pages and media load from `lylyrose.ir`; no `localhost` URLs remain; `vegacodex.ir` untouched.
- **Login is `/secure-login/`**, not `wp-login.php` (WPS Hide Login) — a `wp-login.php` 404 is expected, not a fault.
- **Two silent config bugs blocked the deploy.** Both are fixed on the host **and** now fixed at the source (2026-09-26) — `docker/stage-deploy.sh` generates both and verifies them, so a re-deploy cannot regress:
  1. `wp-config.php` was missing `$table_prefix = 'wp_';` → WordPress ignored the imported tables and 302-redirected every request to `/wp-admin/install.php` (looks like an empty DB when all 97 tables are present).
  2. `.htaccess` shipped with only cPanel's PHP-ini directives, no WordPress rewrite block → every pretty-permalink page 404'd (shop/cart/checkout/login) despite 24 KB of `rewrite_rules` sitting in the DB.
     Gate the next deploy on: home 200 **and** `/shop/ /cart/ /checkout/` 200 — not the home page alone.
- **Hosting credentials**: the cPanel account details are in `.env` (git-ignored) — `PHP_HOST`, `PHP_HOST_USERNAME`, `PHP_HOST_PASSWORD`, `PHP_HOST_IP=89.39.208.244`, `PHP_HOST_SERVER_NAME=ircpanel181`, `LYLYROSE_DOMAIN=lylyrose.ir`, `LYLYROSE_FOLDER=lylyroseir`. The existing site on that account is **vegacodex.ir**; Lyly Rose is an addon, so do not disturb the primary domain's document root.
- **Repository**: local Git initialized and the full tree committed (`1eea959`, 20,302 files) and **pushed** to https://github.com/greatkourosh/lylyrose (`master` tracks `origin/master`). The push needed the PAT from `project_manager/secrets/github.env`; the repo URL now embeds that token, so `git push` works without re-auth. `.env` is git-ignored and was not committed.
- **Local**: `http://localhost:8030`, phpMyAdmin on port 8031; active theme `lylyrose`, plugin `lylyrose-core`. Legacy themes remain unchanged. Database branding still needs verification independently of source-code branding.
- **Core compatibility — RESOLVED 2026-09-26.** WooCommerce 11.1.0 requires WordPress 7.0 or later and calls `WP_Block_Templates_Registry`, a class absent from the 6.5.5 core the shared `aroma_store-wordpress` image ships. The local volume had been hand-upgraded to 7.1 by copying core files out of the upstream container, so the defect only surfaced on a fresh volume — which also could not start at all, for a second reason: the shared image's `aroma-entrypoint.sh` execs the command directly, so the official WordPress entrypoint never ran and never copied core out of `/usr/src/wordpress`. A fresh volume held nothing but `wp-content`. Fixed in `docker/Dockerfile` + `docker/entrypoint.sh`: the image now builds **on** the shared image (keeping its redis extension and wp-content ownership fix) and overlays 7.1 core, asserting at build time that the class exists and the version clears the WooCommerce minimum; the entrypoint keeps the chown and then hands off to the official entrypoint. `docker-compose.yml` builds it as `lylyrose-wordpress:local`. The overlay preserves `wp-config-docker.php` (an image file, not in the WordPress zip) or fresh volumes generate no `wp-config.php`. Verified: fresh volume → 7.1.2, config generated, WooCommerce 11.1.0 activates, `/` `/shop/` `/cart/` 200, `/checkout/` 302, zero fatals; existing stack unaffected (volume still 7.1, all pages 200); suite **217 passed / 0 failed**, twice.
- **Verified fixes**: gift-wrap rendering uses `wp_kses_post(wc_price(...))` instead of the removed theme helper. A real cookie-based add-to-cart request renders the coupon form and nonce. Shop returns 200. Generated JPEG and PNG uploads and their six sizes are WebP.
- **DB rebranded off "Aromaland" (2026-09-26, RESOLVED).** The source project was branded "Aromaland" / "آرومالند" with a misspelled mail domain `admin@aromalnd.test` ("aromalnd"). None of that was visitor-facing in code, but it lived in the DB, so a fresh dump shipped it to production on 2026-09-24 — meaning the earlier claim that "the production site at `lylyrose.ir` is correctly branded" was **wrong**: every page `<title>` read `Aromaland` and `/wp-json/` returned `{"name":"Aromaland"}`. Now fixed on both local and production: `blogname`=`Lyly Rose`, `woocommerce_email_from_name`=`لیلی رز`, all mail addresses -> `info@lylyrose.ir` (also `wp_users` for `admin`/`demo_customer`, one `billing_email`, one comment). Serialized options (`wp_mail_smtp`, `cartflows_ca_email_admin_settings`, `woocommerce_paypal_settings`, `auto_core_update_notified`) were re-serialized rather than SQL-`REPLACE`d, since a blind replace leaves `s:<length>:` prefixes stale and corrupts the blob. The 55-row WP Mail SMTP debug log was purged. **Remaining `aroma` hits are intentional**: 3 `wp_wfconfig` rows that inventory the source project's plugin/theme _slugs_ — rewriting them would misreport what is installed and they are never rendered. Local suite re-verified **217 passed / 0 failed** after the change. Any future dump now carries the correct brand, so the "must fix before re-deploy" concern is closed.
- **Versions (updated 2026-09-26):** core **7.1.2**; WooCommerce **11.1.2**; woo-wallet **1.7.0** (was 1.6.14 — the roadmap's "2.4.x" claim was wrong); redis-cache **3.0.0** (was 2.8.0); Dokan 5.1.3; wp-parsidate 6.4; persian-woocommerce 10.0.5. All 19 updates applied one at a time with a smoke check after each; 19 `active_plugins` unchanged, six shipped-but-inactive plugins updated in place. Updating required two fixes that blocked _all_ updates, now in the image and `docker/update-core.php`: `unzip` was missing from the container (WP's upgrader shells out to it) and core updates need `abort_if_destination_exists => false`. Utilities: `docker/update-core.php`, `docker/update-plugin.php` (one at a time, `--inactive` for shipped-but-off plugins), `docker/smoke.sh`. Rollback backup in `.test-logs/pre-update-backup-20260926-132143/`.
- **Testing**: `bash docker/run-tests.sh` is **fully green — 229 passed, 0 failed** (2026-09-27; 217 before the Incredible Offers port added section 28's 12 checks). Logs in `.test-logs/full-tests-*.log` (project-local, survives reboots; the old `/tmp/lylyrose-full-tests.log` was cleared on reboot). Actual SMS gateway resolves to `PW\PWSMS\Gateways\Logger`; ZarinPal is enabled in sandbox mode.
- **⚠️ The suite was silently broken, then fixed (2026-09-27).** Between the 2026-09-26 runs above and today it reported **105 passed / 109 failed** — not a product regression. Commit `770aaf77` set `siteurl`/`home` to `https://lylyrose.local`, so every `localhost:8030` request 301-redirected to the canonical host and lost the port; the homepage passed only because it tolerates the redirect. Then 5 CSV-export assertions still failed because `WPS Hide Login` redirects `is_admin() && ! is_user_logged_in()` to `/404/`, and the harness's `AUTH_COOKIE`/`"auth"` cookie is not honoured here — only `SECURE_AUTH_COOKIE`/`"secure_auth"` is. Both fixed in `docker/run-tests.sh` (`SITE_URL` default + all four cookie call sites); back to **217/0, twice consecutively**. Detail in `DEVELOPMENT_LOG.md` under "Production update pass: already done, and the test harness was broken".
- **Known remaining port references (deliberate, 2026-09-26).** After the 8080 → 8030 move, `localhost:8080` survives in three places on purpose, and each is a trap to re-introduce later:
  1. `docs/DEPLOYMENT_SUMMARY.md`, `docs/CONTINUATION.md`, `docs/DEVELOPMENT_LOG.md` records of the 2026-09-24 deploy — that deploy really did rewrite 319 `localhost:8080` URLs. Rewriting history would make the record false.
  2. The **inactive** `aroma-store` and `aroma-store-old` themes (`scripts/add_helpers.php`, `style.css` `Theme URI`). **Corrected 2026-09-27:** these are deliberate **rollback/archive copies** — `digikala-v1.0.0` (frozen v1.0.0 snapshot) and `aroma-store-old` are the same kind of thing — documented in `README.md:54-56`, `docs/04_DEPLOYMENT.md:21` and `docs/DEVELOPMENT_LOG.md:425` (2026-09-01, "Theme versioning snapshots"). **Do not delete them** — they are the rollback path. They are **not** forks of the active `digikala` theme and there is no divergence to reconcile, so the "fork risk" previously listed here was a false alarm.

     Precisely: `aroma-store` and `aroma-store-old` are **not** byte-identical, but the only differences are four header strings that exist to mark the copy — `Theme Name: Aroma Store Old`, a `(archive copy)` description, `Version: 2.0.0-old`, and an "archive copy — do not develop here" comment in `functions.php`. Both have the same 24 files with identical names, and no template or logic differs. So: same code, deliberately labelled, nothing to reconcile upstream.

     Editing them here alone *would* fork the repos, which [FEATURE_REQUEST_POLICY.md](FEATURE_REQUEST_POLICY.md) forbids. Tracked upstream as demand **D3**. The `localhost:8080` `Theme URI` in both copies is cosmetic — harmless while inactive, and 8080 is not listening in this repo at all — so fix the string only if already editing those files. The **active** `lylyrose` theme has no port references.
  3. `aroma_store/docker/migrate-from-remote.sh:14` upstream still comments that 8080 "is the separate lylyrose instance" — true when written, false now that Lyly Rose is on 8030. Fix upstream, not here.
- **Updates are LOCAL ONLY.** The versions above are what the local Docker stack runs. **Production at `lylyrose.ir` is still on the pre-update versions** (core 7.1, WooCommerce 11.1.0, woo-wallet 1.6.14, redis-cache 2.8.0, Dokan **5.1.1** — _not_ 5.0.16, see the audit at the top). Nothing has been pushed to the host. Shipping the new versions means a re-deploy via `docker/stage-deploy.sh`, which is a separate, higher-risk step: it rewrites the live database and replaces production plugin code. Do not treat the local green suite as evidence that a production re-deploy is safe — it only says the local stack is correct.
- **Two flaky assertions hardened (2026-09-26)** — both were test defects, not product defects, and both are now fixed in `run-tests.sh`:
  1. **Action Scheduler backlog.** The assertion counted any `pending` action past its `scheduled_date_gmt`. The suite schedules its own async work (`wc_run_batch_process` every minute), so a few actions can sit seconds past due between the sidecar's ticks. Worse, the queue is persisted in the **DB volume**: after a 2-day gap the first run found 14 actions from the previous run still pending, because the cron sidecar had not been running. The check now uses a **5-minute grace period** — a real backlog means cron is broken; a few seconds does not. Start the sidecar (`docker compose up -d`) before the suite or the first run will report a spurious backlog.
  2. **ZarinPal sandbox 500s.** `sandbox.zarinpal.com` intermittently serves an HTML **"Server Error"** page instead of the StartPay payment page — measured at **~1 in 5 requests** under rapid runs against the shared dummy merchant `00000000-0000-0000-0000-000000000000`. The existing retry only covered the _redirect_ into StartPay, not the _token fetch_, so one upstream 500 cascaded into three failures (token, callback, order state). The token fetch now retries up to 6× with a 5s backoff, and the callback/order-state checks are skipped rather than failed when no token was obtained. The gateway itself is healthy — `pg/v4/payment/request.json` returns `code: 100` and a valid authority every time.

- **Safety**: the suite deletes local orders and wallet data; never run against production or Aroma Store. Pre-test database and pre-upgrade core backups are in `/tmp/lylyrose-before-tests.sql` and `/tmp/lylyrose-core-before-upgrade.tar.gz`, excluded from version control. WP-CLI is not currently installed at `/tmp/wp-cli.phar`.
- **Deployment (2026-09-24, complete)**: artifacts were staged locally then pushed to the host. Files went up over **passive FTP** (~25,844 files, 0 failures); the DB import ran as an uploaded **PHP script** (mysqli) over HTTPS because SSH and phpMyAdmin are unavailable. Details and the host-access runbook: [DEPLOY_PREP.md](DEPLOY_PREP.md) and [DEPLOYMENT_SUMMARY.md](../DEPLOYMENT_SUMMARY.md). The DB dump contained real customer/order data and was deleted from the webroot immediately after import, along with every helper/credential file.
- **Host access constraints (re-deploys)**: SSH is **closed** (ports 22 and 2222 filtered), MySQL 3306/3307 closed, phpMyAdmin's API module is not installed, and `Fileman` has no working `extract` (its `fileop extract` shells to `gtar` which fails "Permission denied" on the addon docroot). FTP (21) works **passive only** — Pure-FTPd rejects active mode with "425 No data connection". cPanel (2082/2083) drives everything via UAPI: login, capture the `/cpsess<token>/` session + cookie, then `Mysql::create_database` (param is `name`, **not** `db`), `create_user`, `set_privileges_on_database`, `set_password` (the setter is called `set_password`, not `set_user_password`). `exec()` is disabled host-side, but a PHP file placed in the docroot and fetched over HTTPS runs fine — that is the only way to script the SQL import.
- **MySQL grant gotcha**: after `create_user`, `set_privileges_on_database` can return `status: 1` while the grant is **not actually live**. Symptom: mysqli says "Access denied ... to database X" but a bare connect succeeds and `SHOW DATABASES` omits X. Re-calling `set_privileges_on_database` fixes it. Verify with a PHP `SHOW DATABASES` from the host, not with cPanel's metadata, which falsely reports the user as attached.
- **URL rewrite**: all 319 `http://localhost:8080` occurrences are in **plain** columns (options, GUIDs, postmeta, page content) — **none** are inside WP-serialized blobs, so a plain replace is safe and no length recompute is needed. `siteurl`/`home` are literal plain rows.
- **PHP is 8.1.34 on the host**, not the 8.2 the runbook prefers. WooCommerce 11.1.0's WP 7.0+ requirement is satisfied, so the site works; bump the domain to 8.2 via MultiPHP Manager only if you want parity with the recommendation.
- **Notifications endpoint — fixed and committed (2026-09-26)**: `lylyrose-core` hooked `woocommerce_account_notifications`, but WC fires `woocommerce_account_{endpoint}_endpoint` and only dispatches when a handler is registered there (otherwise it falls back to the dashboard), so the page rendered the account shell with no notification list. The theme `my-account.php` also branched on the endpoint itself and called `do_action('woocommerce_account_notifications')`, duplicating dispatch for every other endpoint; it now just calls `woocommerce_account_content`. The fix was made 2026-09-20 but sat uncommitted in the working tree until 2026-09-26 — it is now in `master` and the suite is green 217/0 (section 25 covers the authenticated render).
- **Next**: the deploy is done and the deploy-template bugs are fixed in a script. What remains is the per-host settings pass (see **Known open items**).
- **Full-suite green root causes fixed & test hardened (2026-09-24)**: the suite went from 207/10 to **217/0**. Four distinct issues: (1) `wp-content/uploads` + `wp-content/cache` were owned `1000:1000` (rebind bind-mount), so the web user (`www-data`/33) could not write — OTP SMS sink, autoptimize cache generation, and media uploads silently failed; fixed with `chown -R 33:33`. (2) The notifications synthetic auth cookie had a stray trailing `|` appended in `run-tests.sh` (`echo '|'`), corrupting the cookie header — fixed. (3) The notifications account endpoint rewrite rule was missing on a fresh volume (self-heal only flushes on version bump), so `/my-account/notifications/` rendered the generic account page; `flush_rewrite_rules()` resolves it. (4) The gift-wrap order test POSTed `payment_method=cod`, but COD isn't enabled (only ZarinPal + wallet) so checkout rejected with "پرداختی انجام نمی شود" — now uses `WC_ZPal` and falls back to the latest order when the pending-order URL lacks `order-received`. Also added a retry to the notifications account-page checks against a transient empty `get_posts` result. (5) The wallet section hardcoded `wp_set_current_user(2)` / `_wc_persistent_cart_2`; after the deploy-prep scrub deleted and the suite recreated `wallet_tester` at a new id, the wallet fee/nav/page checks cascaded (6 failures) — the suite now resolves the uid via `username_exists("wallet_tester")`.

## Aroma Store history (mirrored from upstream)

The entries below were copied and mechanically rebranded from `aroma_store`, which is the
**upstream source of truth** — see [UPSTREAM_RELATIONSHIP.md](UPSTREAM_RELATIONSHIP.md).
Shared architecture, features, and gotchas have their canonical home in
`aroma_store/docs/`; this is a downstream copy kept for local convenience.

Their dates, deployment claims, credentials, fixture IDs, and test counts describe
upstream's history, not verified Lyly Rose results. Do not execute legacy deployment
workarounds or treat these entries as proof of production readiness.

**When these entries and `aroma_store`'s docs disagree, upstream wins** — correct this
copy rather than branching from it.

## Dokan 5.1.1 upgrade + plugin ownership fix (2026-09-12)

- **Problem**: Plugin update «بهروزرسانی افزونه ناموفق بود» on local. Root cause: 21 plugins
  (including `dokan-lite`) were extracted by `docker/install-plugin.sh` running as **root**,
  leaving plugin dirs owned `root:root`. WordPress runs as `www-data` (uid 33), so the
  update's "delete old version" step failed.
- **Fix**: `chown -R 33:33` on all plugin dirs in `wp-content/plugins/`. Verified all 26
  plugins now `www-data:www-data`. Future updates unblocked.
- **Upgrade**: Manually fetched `dokan-lite.5.1.1.zip` (14 MB), extracted, activated.
  WP reports **5.1.1 active**, site health green.
- **Live**: not yet updated — production uses cPanel shared host (plugin dirs owned by
  cPanel user, so ownership issue doesn't apply). Update via WP admin at `/secure-login`
  or FTP chunk+assembler deploy of the new `dokan-lite/` folder.

## Review incentive gotchas (P1 #8)

- `WC_Coupon::set_expiry_date()` does NOT exist in WC 11 — use `set_date_expires( int $timestamp )`.
- woo-wallet registers `comment_post` with **3 args**; firing `do_action("comment_post", $id, 1)` with only 2 in tests causes `ArgumentCountError` (WP core always passes `$commentdata` array as 3rd). Use `do_action("comment_post", $c, 1, get_comment($c, ARRAY_A))`.
- Coupon handler needs the order **actually completed** (`$order->update_status("completed")`) — a bare `do_action("woocommerce_order_status_completed", …)` on a pending order passes the hook but the coupon lookup (`wc_get_orders(status=>completed)`) finds nothing.
- **Local perf quirk**: uncached local pages can take ~18s — Docker→Windows bind-mount
  `stat()` latency (full WP bootstrap ~17s; Super Cache pages unaffected). Live host is
  fine. The suite now uses `--max-time 120 --retry 2` on all curls and section 18
  restores fixture stock before add-to-cart, so runs are deterministic; if something
  still flakes, re-run. Never use `grep -q` on large page HTML in this script (SIGPIPE
  exit 141 under `pipefail`) — use the `html_has` helper.
- **Loyalty wallet**: woo-wallet plugin (fa_IR pack), 2% cashback on completed orders
  (cap 5M, min cart 500k), review credit 50k, topup product 100k–20M Toman; theme adds
  «کیف پول» to the my-account sidebar (endpoint `my-wallet`).
- **OTP login (P0 #1)**: lylyrose-core `ASC_OTP` + theme v1.6.0 two-tab login —
  ورود سریع (mobile + SMS code, auto-registers customers) / ورود با گذرواژه. SMS via
  PWSMS (Logger sink `wc-logs/pwsms.log` until real gateway credentials are set on the
  host). Rate limits: 5/hour per mobile, 60s resend, 5 verify attempts, 120s code TTL.
- **ZarinPal payment (P0 #5)**: gateway `WC_ZPal` configured locally (sandbox yes,
  dummy UUID merchant — sandbox accepts any), section 21 proves the full chain
  checkout → order-pay → receipt → StartPay → callback → processing/paid. ~~On live the
  plugin is present but **disabled**~~ — **corrected 2026-09-27: that was wrong.**
  Production has the gateway **enabled and offering checkout**; a real checkout POST
  created order 1809 and redirected to `sandbox.zarinpal.com` with a real authority,
  on the all-zero dummy merchant. Production still requires a real merchant code +
  `sandbox: no` in WP admin (درگاه‌ها → زرین‌پال) before launch — but the risk is
  "takes orders and cannot collect", not "inert". See the per-host settings table above.
- **Cart abandonment (P0 #4)**: woo-cart-abandonment-recovery v2.1.3 captures email +
  phone at checkout into `wp_cartflows_ca_cart_abandonment`; cron flips stale carts
  (30-min cut-off) to `abandoned`; lylyrose-core `ASC_Cart_Abandonment` sends the
  Persian SMS reminder with tokenized recovery link via PWSMS at that moment; 3
  Persian follow-up email templates (1h/6h/1d) activated; unsubscribed carts skipped.

## Completed milestones

- Review incentive P1 #8 (2026-09-04): lylyrose-core v2.1.0 `ASC_Reviews` —
  on order `completed` schedules Persian review-request email + SMS 3–7 days out
  (Action Scheduler `asc_send_review_request`, once per order via
  `_asc_review_requested`); an approved review by a verified purchaser (email
  matched to a completed order containing that product) earns a single-use 10%
  coupon `REVIEW-XXXXXXXX` (30-day expiry), once per order+product via
  `_asc_review_coupon_{product_id}`; non-purchaser reviews earn nothing. Tests
  section 22 (7 checks). Gotchas: `set_date_expires()` not `set_expiry_date()`;
  `comment_post` needs 3 args (woo-wallet); order must be truly completed for the
  coupon lookup.
- ZarinPal sandbox e2e payment P0 #5 (2026-09-03): gateway configured
  (sandbox yes, dummy UUID merchant accepted by sandbox, IRT ×10 Rial);
  full headless HTTP flow proven — checkout (PWS numeric state 312/city 322 +
  national-ID 0012345679) → order-pay → pay POST → receipt → 302 StartPay →
  payment simulated via verify-token GET on sandbox → callback `/wc-api/WC_ZPal`
  → order processing/paid with transaction id + stock decrement; NOK leaves
  pending. Tests section 21 (10 checks). Live: plugin present but disabled —
  needs real merchant code + `sandbox: no` in WP admin before production.
- Cart abandonment recovery P0 #4 (2026-09-03): woo-cart-abandonment-recovery
  v2.1.3 + `ASC_Cart_Abandonment` SMS glue (hooks `wcf_ca_process_abandoned_order`,
  normalizes phone via `ASC_OTP::normalize_mobile`, sends recovery link + coupon);
  Persian templates replace the English samples; from-name لیلی رز; GDPR notice in
  Persian. Tests section 20 (11 checks). Live needs real SMS gateway credentials.
- Mobile OTP login & registration P0 #1 (2026-09-02): in-house `ASC_OTP` (AJAX
  `asc_otp_request`/`asc_otp_verify`, hashed 6-digit code in a 120s transient,
  60s resend cooldown, 5 attempts, 5 req/hour, auto-register customer with
  `billing_phone`); theme v1.6.0 two-tab login (ورود سریع default) + otp-login.js;
  registration option now `yes`. Tests section 19. Live needs real SMS gateway
  credentials in پیامک settings before production OTP works.
- Loyalty wallet P2 #11 (2026-09-02): woo-wallet plugin v1.5.1 + fa*IR language pack,
  configured via three `\_wallet_settings*\*`option groups — 2% cashback on completed
orders (capped 5M, min cart 500k, refund clawback), review credit 50k (first review
per product per user), topup product «شارژ کیف پول لیلی رز» (100k–20M, partial
payment with auto-deduct, wallet gateway hidden on topup-only carts); theme v1.5.1
my-account sidebar gains «کیف پول» gated on`class_exists('Woo_Wallet_Frontend')`;
endpoint is `my-wallet`(the plugin's menu slug`woo-wallet` 404s on this theme).
  Live deploy done (rewrite flush required after scripted activation); tests section 18.
- Sales reports P2 #15 (2026-09-02): lylyrose-core v2.0.0 `ASC_Reports` — «گزارش فروش» admin page (date-range stats, top products) + CSV export with Persian headers and UTF-8 BOM; signed day-scoped export token instead of nonce (sessions rotate per request); tests section 17.
- Instagram strip P2 #14 (2026-09-02): lylyrose-core v1.9.0 `ASC_Instagram` — `asc_instagram` option (handle + 6 image/post URLs) edited via Persian Settings page; theme v1.5.0 lazy-loaded grid on homepage; no IG API by design; silent until configured; tests section 16.
- Frequently bought together P2 #12 (2026-09-02): lylyrose-core v1.8.0 `ASC_Frequently_Bought` co-purchase query over last 500 completed orders (transient-cached 1 day, invalidated inline on order completion); theme v1.4.0 renders «اکثراً با هم خریداری شده‌اند» on single product with buy-both button + partner cards; silent when no order data (live shows nothing until real orders land); tests section 15.
- Coupon surfaces P1 #10 (2026-09-01): theme v1.3.0 cart shows always-visible Digikala-style coupon field in payment summary (posts to WC core handler with woocommerce-cart nonce); lylyrose-core v1.7.0 `ASC_Coupons` renders `asc_campaign_banner` option as red strip on every page; WELCOME10 (10% individual) seeded; tests section 14.
- WebP image delivery P0 #2 (2026-09-01): library already 100% WebP (Digikala import) so no converter plugin; lylyrose-core v1.6.0 `ASC_Images` converts future JPEG/PNG uploads to WebP via WP core (`image_editor_output_format`), 2560px cap; tests section 13 via docker/image-check.php sideloads a JPEG+PNG and asserts WebP.
- Persian transactional emails P1 #9 (2026-09-01): lylyrose-core v1.5.0 `ASC_Emails` — gettext_woocommerce map (~40 English email_improvements-era default strings → Persian; fa_IR pack misses them), woocommerce_email_styles filter injects `direction:rtl`, email options branded (base #ef394e, Tahoma, header right, from-name لیلی رز, Persian footer); tests section 12 renders a real email via docker/email-check.php.
- Store pages P1 #6 (2026-09-01): about/contact/track-order/faq self-created by lylyrose-core v1.4.0 (ASC_Store_Pages), Persian templates in theme v1.2.0, footer links live; order-lookup + contact-form handlers with rate limit; tests section 11.

- Full Digikala-style UI: home, shop/category (URL-driven filters), single product
  (sku-code URLs via `ASC_Product_Code`), cart, checkout (national-ID field),
  my-account, wishlist, demo-mode banner on every page.
- Rebrand: all user-visible دیجی‌کالا labels → لیلی رز (templates + 100 product
  descriptions in DB, local and live).
- Deployment to cPanel host complete; demo-mode banner, home-page real data,
  `sort=discount` all live.

## Known open items

- ~~**Fix the two deploy-template bugs in the local source**~~ — **DONE 2026-09-26.**
  `docker/stage-deploy.sh` now builds the whole deploy artifact set (DB dump, uploads,
  fa*IR core, repo `wp-content`, `wp-config.php`, `.htaccess`) and refuses to report
  success unless `$table_prefix = 'wp*';`and the`# BEGIN WordPress`block are both
present. It also`php -l`s the generated config, because a config that fails to parse
breaks the site in the same misleading way (redirect to `install.php`). Verified end to
  end: 25,845 files / 452 MB, 16/16 checks pass. The tree is assembled from the script
  now, so a re-deploy cannot silently regress. DB credentials ship as placeholders
  (cPanel may prefix the db name/user); fill them on the host.
- **Per-host settings pass — audited on the live site 2026-09-27 14:16–14:43 UTC.**
  Read off production with read-only docroot probes (random name, uploaded, fetched,
  deleted; plus one guarded write to clean up the test order, see below). **Three
  items on the old list are already done, and one is not what the docs said:**

  | Item | Reality on the live site |
  | --- | --- |
  | Redis | **Done already.** Plugin already deactivated, and **no `WP_REDIS_*` defines exist** and **no `object-cache.php` drop-in**. `WP_Object_Cache` is the live backend. The "drop the defines" line is moot — there is nothing to drop. |
  | UpdraftPlus remote storage | **Superseded.** Still no remote destination, deliberately (free route, see the runbook). 1a/1b/1c cover it. |
  | Wordfence | **Wordfence is INACTIVE** (not active-and-unconfigured as listed). `wordfence` option empty. It is installed, shipped-but-off. |
  | WP Super Cache | **Genuinely off**: `WP_CACHE` is `false` and there is no `advanced-cache.php` and no `supercache` dir. Needs enabling, not "re-configuring". |
  | PWSMS | **Confirmed `Logger`** (`PW\PWSMS\Gateways\Logger` in `pwsms_settings`), so SMS is a silent no-op and the P0 OTP login does not work live. Unchanged and still the highest-value gap. |
  | WP Mail SMTP | **Empty** — no provider, no from-address, no SMTP host or key. Unconfigured. |
  | ZarinPal | **Enabled and live at checkout — contradicting two docs.** See below. |

  **ZarinPal is not "disabled and inert", and it is not "sandbox, so it does not
  matter".** The gateway's settings say `enabled: yes`, the plugin loads
  (`WC_ZPal` class exists even though it is not in `active_plugins` — it is
  loaded some other way), and a real checkout POST created order **1809** and
  redirected to `https://sandbox.zarinpal.com/pg/StartPay/…` with a real
  authority ID. The merchant code is the **all-zero dummy UUID** and `sandbox`
  is `yes`, so no money can ever be collected — but orders *are* created and
  customers *are* sent to a payment page. That is a materially different risk
  from "the gateway is off": it looks like a working checkout to a visitor.

  **What keeps this from being an emergency: the store is in demo mode.** The
  hardcoded banner in `header.php` states «سفارش‌ها واقعی نیستند و پرداختی انجام
  نمی‌شود» (orders are not real and no payment is made), and the badge says
  «خرید نهایی ثبت نمی‌شود». So the site is deliberately pre-launch, which is
  consistent with taking sandbox payments. **Before launch, ZarinPal must get a
  real merchant code and `sandbox: no`, or checkout must be disabled** — the
  demo banner is a visitor-facing statement, not a safety control, and anyone
  reaching the site without reading it would see a live-looking checkout.

  **Cleanup note:** the audit created order 1809 (7,800,000 تومان, product 1301,
  `audit-probe@example.invalid`). It was **cancelled with an audit note and
  trashed** — not force-deleted, so it is recoverable in wp-admin — and stock
  was restored. Confirmed `post_status = trash` in the DB. One side effect to
  be aware of: WooCommerce sent the store's real «سفارش لغو شده» cancellation
  email to the admin address, so the owner will see one cancellation email for
  order 1809. `/checkout/order-received/1809/` still renders "thank you" from
  the page cache even though the order is trashed — cosmetic, not a data leak.

  Three probes were burned on this audit, all mine and all the same mistake as
  `updraft_interval_type`: reading a value from the wrong place instead of
  checking. `sms_main_settings` is a 0-length placeholder — the real gateway
  option is `pwsms_settings`; `function_exists('UpdraftPlus_Options')` is always
  false for a class; and `WC()->payment_gateways` is empty from a bare
  `wp-load.php` because WooCommerce only populates it on `woocommerce_init`.
  In all three cases the *site* was fine and my probe was wrong, which is the
  safer direction — but it is also why the gateway list had to be read from the
  rendered checkout HTML rather than from the registry.

- **Per-host settings still to do from `/secure-login/`** (no SSH needed, just admin access):
  ZarinPal real merchant code + `sandbox: no` **before launch**; PWSMS real gateway
  credentials (still the `Logger` sink, so production SMS is a no-op); WP Mail SMTP
  credentials; WP Super Cache **enable** (`WP_CACHE` is false today); Wordfence
  **activate + configure** (it is inactive). Redis needs nothing — see the table above.
- **Cron**: `DISABLE_WP_CRON` is `false` in the deployed config (WP self-triggers). If you
  want a cPanel cron job instead, set it to `true` and add
  `* * * * * /usr/local/bin/php /home3/bqwyvowk/lylyroseir/wp-cron.php >/dev/null 2>&1`.
- **Live Dokan update pending**: the imported DB still has Dokan 5.0.16 (local is on 5.1.3 as of 2026-09-26; was 5.1.1). **Superseded by the 2026-09-26 audit above — production is on 5.1.1, not 5.0.16, and 5.1.3 is available.**
  Upgrade via WP admin or an FTP chunk+assembler deploy of the new `dokan-lite/` folder,
  then re-verify home 200 + shop 200.
- **PHP version**: host runs 8.1.34; runbook prefers 8.2. Optional.
- See [FEATURES_ROADMAP.md](FEATURES_ROADMAP.md) for prioritized next features.

## Task: deploy lylyrose to the host — ✅ DONE 2026-09-24

> Retained for reference. All 13 steps below completed. The site is live at
> `https://lylyrose.ir`; see the verified state at the top of this file. Re-deploys must
> work around the host constraints recorded above (no SSH, passive FTP only, scripted PHP
> SQL import) and must include the two template fixes.

Goal: serve the rebranded store at `https://lylyrose.ir` from the existing cPanel
account, without touching the `vegacodex.ir` primary site.

Host facts are in `.env` (git-ignored): `PHP_HOST_IP=89.39.208.244`,
`PHP_HOST_USERNAME=bqwyvowk`, `PHP_HOST_PASSWORD=…`, cPanel at
`http://cp181.unitedhost.org:2082`, server name `ircpanel181`, DNS
`ns875`/`ns876.mihanwebhost.com`, addon folder `lylyroseir`. The real docroot on
this host is `/home3/bqwyvowk/lylyroseir` (note `home3`, not `home`).

### Steps (as planned)

1. **DNS** — point `lylyrose.ir` at the host: `A` record to `89.39.208.244`, or NS
   records to `ns875`/`ns876.mihanwebhost.com` if the registrar delegates. Confirm with
   `dig +short lylyrose.ir` before continuing; cPanel will not issue an addon domain's
   TLS certificate until the domain resolves here.
2. **Addon domain** — cPanel → Domains → Create A New Domain: `lylyrose.ir` with
   document root `lylyroseir`. Verify it created `/home/bqwyvowk/lylyroseir` (or the
   cPanel-reported path) and that `vegacodex.ir` still serves.
3. **PHP** — set PHP 8.1+ (8.2 preferred) for the addon domain via MultiPHP Manager,
   and `upload_max_filesize`/`post_max_size=64M`, `max_execution_time=300`,
   `memory_limit=256M` via MultiPHP INI Editor.
4. **WordPress core** — download the fa_IR package locally
   (`https://fa.wordpress.org/latest-fa_IR.zip`), unzip, and upload its contents
   (minus `wp-content`) into `lylyroseir/`. Do **not** use the Docker image's
   WordPress 6.5.5 core — WooCommerce 11.1.0 needs WP 7.0+, and the local volume was
   upgraded to 7.1.
5. **wp-content** — upload `wordpress/wp-content/` from the repo (themes `lylyrose`,
   `digikala-v1.0.0`, `aroma-store`; plugins including `lylyrose-core`; `languages`).
   **Exclude** the runtime files that must be regenerated on the new host:
   `advanced-cache.php`, `object-cache.php`, `wp-cache-config.php`, and
   `wp-content/uploads/` (uploads are git-ignored — migrate them separately in step 7).
   `docker/migrate-from-remote.sh` and the chunk+assembler flow in
   [04_DEPLOYMENT.md](04_DEPLOYMENT.md) are the existing patterns for this.
6. **Database** — create db + user in cPanel, then export local and import:
   `docker exec lylyrose-db mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers lylyrose > lylyrose.sql`.
   The dump contains customer/order data — move it over an encrypted channel and
   delete it from any shared location right after import.
7. **Uploads** — `docker cp lylyrose-wp:/var/www/html/wp-content/uploads ./uploads`,
   then upload to `lylyroseir/wp-content/uploads/`. Set permissions to the cPanel
   user (usually `644` files / `755` dirs, no `www-data`).
8. **wp-config.php** — set DB credentials and `WP_HOME`/`WP_SITEURL` to
   `https://lylyrose.ir`; fresh salts from `https://api.wordpress.org/secret-key/1.1/salt/`;
   add `DISALLOW_FILE_EDIT`, `FS_METHOD=direct`, `WP_MEMORY_LIMIT=256M`.
   **Required and easy to miss: `$table_prefix = 'wp_';`** (normally added by the WP
   installer). Without it WordPress ignores every imported table and redirects to
   `install.php`. Keep the dump's prefix and this line in sync.
9. **URL rewrite** — replace `http://localhost:8030` with `https://lylyrose.ir` across
   all tables (WP-CLI `wp search-replace … --all-tables --precise`, or Better Search
   Replace). Verify `wp_options.siteurl` and `home` afterwards.
10. **TLS** — AutoSSL in cPanel → SSL/TLS Status; force HTTPS once issued.
11. **Permalinks & cron** — flush rewrite rules (Settings → Permalinks → Save); add a
    cPanel cron job hitting `wp-cron.php` every minute. cPanel's addon-domain
    `.htaccess` contains only PHP-ini directives, so the `# BEGIN WordPress` rewrite
    block must be present **below** them or every pretty permalink 404s.
12. **Re-point per-host settings** — Redis Object Cache **deactivate** (no Redis on
    shared hosting); WP Super Cache re-configure; UpdraftPlus re-point remote storage;
    WP Mail SMTP re-enter credentials; **ZarinPal** — real merchant code and
    `sandbox: no` (currently sandbox-only); **Persian SMS** — real gateway credentials
    (currently the `Logger` sink); Wordfence scan + firewall mode; confirm
    `/secure-login` loads.
13. **Verify** — home 200, shop 200, single product, add-to-cart, cart, checkout,
    order created, admin order visible, media resolving from `lylyrose.ir` (no
    `localhost` URLs left). Check `vegacodex.ir` is untouched.

### Notes

- **Do not run `docker/run-tests.sh` against production** — the suite deletes orders
  and wallet data.
- Plugin dirs on cPanel are owned by the cPanel user, so the local
  `root:root` ownership bug does not apply here.
- The prior "deployment to cPanel host complete" claim in the inherited Aroma Store
  history below describes the **source** project. Those steps must still be run for
  Lyly Rose.

## 2026-09-14 — Dokan add-to-cart fix + suite re-green (in progress)

- **Dokan own-product bug**: imported products have `post_author = 0`; Dokan's
  `dokan_vendor_own_product_purchase_restriction` matches author 0 to the guest (0)
  and force-marks every product non-purchasable → add-to-cart broken for everyone.
  Fixed by filtering `dokan_is_product_author` (return -1 for author-0) in
  lylyrose-core (single copy; the duplicated theme copy was removed).
- **Test fixtures made dynamic**: run-tests.sh now resolves FP_MAIN (price 9,940,000 =
  old fixture 137 / SKU DIGIKALA-20599667; 2% cashback = 198,800), FP_OOS, FP_ALT,
  FP_NEG at runtime instead of hardcoding 137/138/139/17/11/242. FP_MAIN gets
  manage_stock=true so the stock assertions work.
- **State/city validation**: rebuilt DB lost PWS numeric state/city terms — checkout
  POSTs now use `billing_state=THR` + `billing_city=تهران` (free text).
- **Post-rebuild config wave 2 restored**: WC email branding (base #ef394e / Tahoma /
  from-name لیلی رز), cartflows 3 Persian abandonment templates + admin/GDPR settings,
  woo-wallet `is_auto_deduct_for_partial_payment=on` (partial fee -248800 verified).
- **Redis ext**: pecl.php.net reachable again — redis 6.3.0 installed in container,
  `RUN pecl install redis` added to docker/Dockerfile; object cache truly connects now.
- **Theme fix**: `lylyrose_stock_notifier_script` used `global $product` which is empty
  at `wp_enqueue_scripts` (loop runs after get_header) — now resolves via
  `get_queried_object()`. stock-notifier.js enqueues on OOS pages again.
- **Theme/plugin version**: lylyrose-core header synced to 2.3.0 (const) — WP now
  reports the real version.

**Uncommitted in `lylyrose`'s working tree as of 2026-09-28 (later) — checkout
duplicate-render fix. Do not mistake these for a peer session's in-flight work.**
Requested against `https://lylyrose.ir/checkout/` («بخش خلاصه سفارش هم مرتب بشه»).
The order summary was not untidy, it was **duplicated — and live since
2026-09-05**, when multi-step checkout landed:

| | before | after |
| --- | --- | --- |
| `.woocommerce-checkout-review-order-table` | **3** | 1 |
| `.woocommerce-checkout-payment` | **2** | 1 |
| `#place_order` | **2** (duplicate DOM id) | 1 |

`woocommerce_checkout_order_review` is a two-callback action
(`woocommerce_order_review` @10, `woocommerce_checkout_payment` @20), but the
theme called it *as well as* `woocommerce_order_review()` in the same aside and
fired it again in step 2 — so customers saw the payment panel twice and the green
«ثبت سفارش» button twice. The sidebar now renders the table only; step 2 calls
`woocommerce_checkout_payment()` directly. Files: `themes/lylyrose/woocommerce/
checkout/form-checkout.php` (2 lines) and `docker/run-tests.sh` (section 24, +5
checks proven to fail against `HEAD`'s files first).

**Second, pre-existing bug found while verifying it:** the stepper's `is-hidden`
rule was scoped `.dk-checkout-step--2`, but the JS adds that class to whichever
step is *not* current — so «ادامه به پرداخت» never actually hid step 1 and both
cards stayed stacked. Re-scoped to `.dk-checkout-step` in `themes/lylyrose/
style.css`. Verified by measurement: step 1 `display: none`, step 2 visible.

Suite: **255 passed, 1 failed** — the failure is `media library contains non-WebP
images`, which is the 4 gift-card PNGs seeded 2026-09-28 12:01 by the D2
gift-card feature (ids 2849–2852) and is unrelated to checkout. Do not attribute
it to this change.

Not addressed, deliberately: the checkout page has a 90px horizontal overflow at
390px from `.dk-announce`, but home and `/shop/` show the identical 90px — it is a
site-wide announcement-bar problem, not a checkout one.
