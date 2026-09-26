# Lyly Rose Production Deployment Summary

> **Upstream:** this project is **downstream** of `aroma_store`, which is the source of
> truth. Develop and test features upstream first; mirror here only when needed.
> See [UPSTREAM_RELATIONSHIP.md](UPSTREAM_RELATIONSHIP.md).


**Prepared:** 2026-09-20
**Deployed:** 2026-09-24 — `https://lylyrose.ir` is **live and verified**
**Target:** `https://lylyrose.ir` (addon domain on vegacodex.ir hosting)

---

## ✅ Deployment complete (2026-09-24)

| Step | Status | Notes |
|------|--------|-------|
| DNS | ✅ Done | `lylyrose.ir` → `89.39.208.244` verified |
| Addon domain | ✅ Done | docroot `lylyroseir` = `/home3/bqwyvowk/lylyroseir` |
| Files | ✅ Done | 25,844 files / 342 MB over **passive FTP**, 0 failures |
| Database | ✅ Done | `bqwyvowk_lylyrose` created; 1,218 statements imported, 97 tables, 0 failures |
| wp-config.php | ✅ Done | real credentials, fresh salts, prod URLs |
| URL rewrite | ✅ Done | 319 `localhost:8080` → `https://lylyrose.ir`; none were serialized |
| `.htaccess` rewrites | ✅ Done | WordPress rewrite block appended under cPanel's directives |
| Verification | ✅ Done | home / shop / cart / checkout / my-account / secure-login all 200; add-to-cart works; media loads; `vegacodex.ir` untouched |

⚠️ **Two config bugs were found and fixed on the host but are still unfixed in the
local source templates** — fix before re-deploying:

1. `wp-config.php` was missing `$table_prefix = 'wp_';` → every request redirected to
   `install.php` despite the database being fully imported.
2. `.htaccess` shipped with no WordPress rewrite block → every pretty-permalink page
   404'd. (Details in [docs/DEPLOY_PREP.md](docs/DEPLOY_PREP.md).)

### Still to do (needs WP admin at `/secure-login/`)

- [ ] ZarinPal real merchant code + `sandbox: no`
- [ ] PWSMS real gateway credentials (currently the `Logger` sink — production SMS is a no-op)
- [ ] WP Mail SMTP credentials, UpdraftPlus remote storage
- [x] Deactivate Redis Object Cache — **done 2026-09-26.** It was never doing
      anything: drop-in not installed, object cache "Not enabled", Redis unreachable.
      Deactivated via REST; 19 → 18 active plugins, all key pages still 200.
      (`WP_REDIS_*` defines still in `wp-config.php` — inert without the plugin,
      but worth dropping for tidiness when convenient.)
- [ ] WP Super Cache re-configure, Wordfence scan + firewall mode
- [ ] Live Dokan update — **doc was stale.** Production is on **5.1.1**, not
      5.0.16; 5.1.3 is available. Fold into the 23-item update pass below.
- [ ] **23 pending updates** (20 plugins, 3 themes, core 7.1 → 7.1.2), plus a
      **failed auto-update recorded on the dashboard**. Not run — needs a verified
      restorable backup first, and UpdraftPlus currently has no remote target.
- [ ] Optional: bump the domain to PHP 8.2 (host currently runs 8.1.34)

---

## Original staging notes (pre-deploy, for re-staging)

| Step | Status | Notes |
|------|--------|-------|
| Database dump | ✅ Done | `/tmp/lylyrose.sql` (1.6 MB) |
| Uploads archive | ✅ Done | `/tmp/lylyrose_uploads/` |
| WordPress 7.1 core (fa_IR) | ✅ Done | `/tmp/lylyrose_deploy/` (excludes wp-content) |
| wp-content (themes, plugins, languages) | ✅ Done | `/tmp/lylyrose_deploy/wp-content/` (excludes runtime files) |
| wp-config.php template | ⚠️ Fix | needs `$table_prefix = 'wp_';` before re-deploy |
| Fresh salts | ✅ Done | Embedded in wp-config.php |

---

## 📦 Deployment Package Location

```
/tmp/lylyrose_deploy/          # Complete WordPress install (core + wp-content)
/tmp/lylyrose.sql              # Database dump
/tmp/lylyrose_uploads/         # Uploads directory
```

---

## 🔧 Steps 2–13 as actually executed

The original runbook assumed SSH, SFTP and phpMyAdmin. **This host has none of them** —
see the host-access notes at the end of this file for what actually works. The steps as
performed:

| # | Step | How it was actually done |
|---|------|--------------------------|
| 2 | Addon domain | cPanel UAPI `Domains::add_addon_domain`; docroot `/home3/bqwyvowk/lylyroseir` |
| 3 | PHP config | Host runs **PHP 8.1.34** (not 8.2); `upload_max_filesize = 64M`, `memory_limit = 256M` via `.htaccess`/INI editor |
| 4 | Database | `bqwyvowk_lylyrose`; grant needed a **second** `set_privileges_on_database` call to take effect |
| 5 | Upload files | `ftplib` with `set_pasv(True)` — **passive only**, active mode gets `425 No data connection` |
| 6 | Import database | PHP script in the docroot run over HTTPS (mysqli). `exec()` is disabled and `gtar` is not permitted, so no shell out |
| 7 | Upload media | Same FTP pass; files `644`, dirs `755` |
| 8 | wp-config.php | Real credentials + fresh salts — **and `$table_prefix = 'wp_';`**, which the installer would normally add |
| 9 | URL rewrite | 319 `localhost:8080` → `https://lylyrose.ir`. All in plain columns, none serialized, so a plain replace is safe |
| 10 | TLS/SSL | AutoSSL already active for the addon domain; HTTP redirects to HTTPS |
| 11 | Permalinks & cron | cPanel `.htaccess` had only PHP-ini directives — the `# BEGIN WordPress` block was appended **below** them. Cron: `DISABLE_WP_CRON` is currently `false`, so WP-Cron still runs on page loads |
| 12 | Per-host settings | Deferred — see "Still to do" above |
| 13 | Verify | All checks passed |

---

## ⚠️ Important Notes

1. **NEVER run `docker/run-tests.sh` against production** — deletes orders and wallet data
2. **Plugin ownership**: On cPanel, plugin dirs owned by cPanel user — no `chown` needed
3. **The "deployment complete" note in CONTINUATION.md refers to Aroma Store (source project), NOT Lyly Rose**
4. **Database contains customer/order data** — move over encrypted channel, delete after import
5. **The dump held real customer/order data.** Delete it from every location (local `/tmp`, the
   host docroot, and any `*.sql`/`*.sql.gz`) immediately after the import is verified
6. **Login is `/secure-login/`, not `wp-login.php`** (WPS Hide Login). A `wp-login.php` 404 is
   expected, not a fault
7. **Never leave helper scripts in the production webroot** — the SQL importer, its `.dbpass`
   sibling, and any `diag.php`/`cfgcheck*.php` were removed after use. If a re-deploy needs
   them again, re-upload, run, and delete in the same session

---

## 🔐 Host access

Credentials live in the git-ignored `.env` (see `.env.example` for the variable names).
Non-secret identifiers for reference:

| Item | Value |
|------|-------|
| cPanel URL | `http://cp181.unitedhost.org:2082` |
| cPanel user | `bqwyvowk` |
| Server IP | `89.39.208.244` |
| Server name | `ircpanel181` |
| DNS | `ns875/ns876.mihanwebhost.com` |
| Docroot | `/home3/bqwyvowk/lylyroseir` |
| Addon folder | `lylyroseir` |
| Target domain | `lylyrose.ir` |
| Database | `bqwyvowk_lylyrose` |

### What is blocked on this host

- **No SSH**, and ports 3306/3307 are closed
- **No phpMyAdmin** (neither UI nor API)
- `Fileman` has no `extract` function; `fileop op=extract` runs `gtar` but fails with
  *Permission denied* on the addon docroot — no server-side untar
- FTP is **passive-mode only**
- `exec()` is disabled, but **PHP in the docroot runs over HTTPS** — this is what made the
  scripted SQL import possible
- cPanel UAPI function names that differ from the obvious guess: `set_privileges_on_database`
  (not `grant_user_privileges`), `set_password` (not `set_user_password`)

See [docs/CONTINUATION.md](docs/CONTINUATION.md) for the full handoff.
