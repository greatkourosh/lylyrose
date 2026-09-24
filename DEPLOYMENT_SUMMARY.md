# Lyly Rose Production Deployment Summary

**Prepared:** 2026-09-20  
**Target:** `https://lylyrose.ir` (addon domain on vegacodex.ir hosting)

---

## ✅ Already Completed

| Step | Status | Notes |
|------|--------|-------|
| DNS | ✅ Done | `lylyrose.ir` → `89.39.208.244` verified |
| Database dump | ✅ Done | `/tmp/lylyrose.sql` (1.6 MB) |
| Uploads archive | ✅ Done | `/tmp/lylyrose_uploads/` |
| WordPress 7.1 core (fa_IR) | ✅ Done | `/tmp/lylyrose_deploy/` (excludes wp-content) |
| wp-content (themes, plugins, languages) | ✅ Done | `/tmp/lylyrose_deploy/wp-content/` (excludes runtime files) |
| wp-config.php template | ✅ Done | `/tmp/lylyrose_deploy/wp-config.php` (needs DB password) |
| Fresh salts | ✅ Done | Embedded in wp-config.php |

---

## 📦 Deployment Package Location

```
/tmp/lylyrose_deploy/          # Complete WordPress install (core + wp-content)
/tmp/lylyrose.sql              # Database dump
/tmp/lylyrose_uploads/         # Uploads directory
```

---

## 🔧 Remaining Steps (cPanel/Host)

### Step 2: Addon Domain
- cPanel → Domains → Create A New Domain
- Domain: `lylyrose.ir`
- Document Root: `lylyroseir`
- Verify `/home/bqwyvowk/lylyroseir` created

### Step 3: PHP Configuration (MultiPHP Manager + INI Editor)
- PHP Version: **8.2** (or 8.1 minimum)
- `upload_max_filesize = 64M`
- `post_max_size = 64M`
- `max_execution_time = 300`
- `memory_limit = 256M`

### Step 4: Database Setup
- cPanel → MySQL Databases
- Create database: `lylyrose` (or prefixed, e.g., `bqwyvowk_lylyrose`)
- Create user with **ALL PRIVILEGES**
- Note the actual DB name, user, password for wp-config.php

### Step 5: Upload Files
Upload `/tmp/lylyrose_deploy/` contents to `lylyroseir/` via:
- **Option A**: cPanel File Manager (zip + upload + extract)
- **Option B**: FTP/SFTP (FileZilla, WinSCP)
- **Option C**: rsync over SSH if available

### Step 6: Import Database
- cPanel → phpMyAdmin
- Select the new database → Import → `/tmp/lylyrose.sql`
- **Delete the SQL file from server after import**

### Step 7: Upload Media
Upload `/tmp/lylyrose_uploads/` to `lylyroseir/wp-content/uploads/`
- Permissions: files `644`, dirs `755`, owned by cPanel user

### Step 8: Finalize wp-config.php
Edit `/home/bqwyvowk/lylyroseir/wp-config.php`:
```php
define( 'DB_NAME', 'ACTUAL_DB_NAME' );
define( 'DB_USER', 'ACTUAL_DB_USER' );
define( 'DB_PASSWORD', 'ACTUAL_DB_PASSWORD' );
```

### Step 9: URL Rewrite (if not already https://lylyrose.ir in DB)
- If DB was imported from localhost, run search-replace:
- `wp search-replace 'http://localhost:8080' 'https://lylyrose.ir' --all-tables --precise`
- Or use Better Search Replace plugin after login

### Step 10: TLS/SSL
- cPanel → SSL/TLS Status → Run AutoSSL for `lylyrose.ir`
- Force HTTPS once issued

### Step 11: Permalinks & Cron
- WP Admin → Settings → Permalinks → Save Changes
- cPanel → Cron Jobs: `* * * * * /usr/local/bin/php /home/bqwyvowk/lylyroseir/wp-cron.php >/dev/null 2>&1`

### Step 12: Re-configure Per-Host Settings
| Plugin/Setting | Action |
|----------------|--------|
| Redis Object Cache | **Deactivate** (no Redis on shared hosting) |
| WP Super Cache | Re-configure |
| UpdraftPlus | Re-point remote storage |
| WP Mail SMTP | Re-enter credentials |
| ZarinPal | Real merchant code + `sandbox: no` |
| Persian SMS (PWSMS) | Real gateway credentials |
| Wordfence | Scan + firewall mode |
| `/secure-login` | Confirm loads |

### Step 13: Verify
- ✅ Home page 200
- ✅ Shop page 200
- ✅ Single product
- ✅ Add to cart
- ✅ Cart page
- ✅ Checkout → order created
- ✅ Admin order visible
- ✅ Media URLs resolve to `lylyrose.ir`
- ✅ `vegacodex.ir` untouched

---

## ⚠️ Important Notes

1. **NEVER run `docker/run-tests.sh` against production** — deletes orders and wallet data
2. **Plugin ownership**: On cPanel, plugin dirs owned by cPanel user — no `chown` needed
3. **The "deployment complete" note in CONTINUATION.md refers to Aroma Store (source project), NOT Lyly Rose**
4. **Database contains customer/order data** — move over encrypted channel, delete after import

---

## 📋 Quick Commands for cPanel Terminal/SSH

```bash
# If you have SSH access:
cd ~/lylyroseir

# Fix permissions after upload
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Flush permalinks (if WP-CLI available)
wp rewrite flush --hard

# Verify DB connection
wp db check
```

---

## 🔐 Credentials Reference (from .env)

| Variable | Value |
|----------|-------|
| cPanel URL | `http://cp181.unitedhost.org:2082` |
| cPanel User | `bqwyvowk` |
| cPanel Pass | `Gamba@Ozaka` |
| Server IP | `89.39.208.244` |
| Server Name | `ircpanel181` |
| DNS | `ns875/ns876.mihanwebhost.com` |
| Addon Folder | `lylyroseir` |
| Target Domain | `lylyrose.ir` |