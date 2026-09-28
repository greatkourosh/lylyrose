# Production update runbook — `lylyrose.ir`

**Status: ALL DONE as of 2026-09-28.** Steps 2–5 were completed outside this repo;
step 1a/1b backup is done and verified; **1c (UpdraftPlus daily schedule) is set,
its cron events are registered, and the first real fire has produced a verified
backup** — see 1c below.

**Do not re-run steps 2–5.** They are already complete — see the audit at the top
of `CONTINUATION.md`. The 23 pending updates this page was written about **no
longer exist**: production is on core 7.1.2, WooCommerce 11.1.2, Dokan 5.1.3,
woo-wallet 1.7.0, with **0 plugins and 0 core updates pending**. What remains is
3 unused default block themes, deliberately not touched. The failed auto-update
notice is gone. This page is kept as the record of how that was unblocked, and
because the backup steps in it still apply before any future risky change.

Step 1 was rewritten 2026-09-27 to the **free route** (host-local backup + FTP pull
to this machine). It no longer requires a paid UpdraftVault subscription or any
third-party account.

**Update 2026-09-27:** step 1a and **1b are both DONE and verified.**

- **1a (host):** `~/backup-9.27.2026_09-37-51_bqwyvowk.tar.gz` — 389.6 MB, size
  stable across a 30s re-check, so the job finished rather than still writing.
- **1b (off-host):** pulled to
  `backups/production-2026-09-27/backup-9.27.2026_09-37-51_bqwyvowk.tar.gz`.
  Local size equals the host's FTP `SIZE` exactly (408,520,120 bytes), the gzip
  stream decompresses fully, and it contains `mysql/bqwyvowk_lylyrose.sql` plus
  `homedir/lylyroseir/wp-config.php` and 1,794 upload files.
  sha256 `a8c2a1173aaa185ea4913d39fd9cf90b442d00c178926bb0ef8f720b1f726e3f`.

The update pass is no longer blocked on backup. **1c is done and its mechanism is
verified** (UpdraftPlus daily schedule set 2026-09-27, both backup cron events
registered and recurring, and the site's cron loopback confirmed working — see
1c below). **The first real fire also happened: on 2026-09-28 04:15 UTC it
produced a complete 115.2 MB set**, verified in the log, by checksum, and against
the live directories. Only 1a-ii (UpdraftPlus host-local, optional) remains,
and it is optional.

**1c's original cPanel route does not exist on this host** — no Backup schedule
control, no Backup Wizard, and the `Cron` UAPI module is not installed at all.
The 1c that was done is therefore UpdraftPlus-only, and it was done with the same
host-side PHP probe mechanism the 2026-09-26 rebrand used. Details in 1c below.

Every step below names how to verify it, because the thing that went wrong before
was a step that "succeeded" silently — including, twice, this page's own version
claims, which were stale in the direction of "still pending" when the live site had
already moved on.

Last verified live: **2026-09-27**. Production core **7.1.2**, WooCommerce
**11.1.2**, Dokan **5.1.3**, woo-wallet **1.7.0**, redis-cache 3.0.0 (inactive,
correctly inert — no drop-in). Site health: `/` `/shop/` `/cart/` `/checkout/`
`/my-account/` `/secure-login/` all 200, and a real cart → checkout round trip
works (7,950,000 تومان in cart, place-order button present).

---

## Step 1 — Take a backup (blocks everything else)

**Why this is first and not optional:** UpdraftPlus is active on production, which
makes it look like backups are covered. They are not.

- `updraft_interval` = **manual** — no scheduled backup has ever run.
- Backup history is **empty**.
- The only configured destination, **UpdraftVault**, has `email: ""` and an unknown
  quota. It is configured but **never connected**.

So production has **no backup, local or remote**, right now. Running 23 updates
against that is not a reasonable risk on a store taking payments.

This step costs nothing and needs no paid account. Two different risks are at
stake and they are not the same risk, so they are covered separately:

- **A bad update breaks the site** — the risk you are about to take in steps 2–5.
  A copy on the *same host* fully covers it. This is what the update pass needs.
- **The host disappears** — a same-host copy does not help here, and no free
  method guarantees this. What is available is a copy on your own machine, pulled
  over the FTP access you already use to deploy. That is off the web host and is
  the best free option that exists. It does not protect against losing your own
  machine, which is the risk you accept by not paying for remote storage.

**UpdraftPlus itself is free** — it is a GPL plugin, and scheduling + local
retention are free features. Only the **UpdraftVault storage tier** is a paid
subscription, and step 1a skips it entirely by leaving destinations empty. So
there is no reason to replace the plugin. What was missing was never a licence;
it was that nothing was ever scheduled and nothing was ever taken.

### Do this — 1a. cPanel Backup (primary — includes the database)

The host's own **cPanel Backup** is available on this account and is free. It
writes a full account archive into the account home directory:

`~/backup-<date>_<time>_bqwyvowk.tar.gz`

Unlike UpdraftPlus host-local, it captures **all** account databases and the whole
site tree, so it is the stronger of the two. Verified working on this host
2026-09-27 — the `Backup/fullbackup_to_homedir` UAPI call returned `status: 1`
and produced a stable 389.6 MB archive.

1. cPanel → **Backup Wizard**, or the Backup module. Choose to back up the
   account, and put the archive in the home directory rather than off-server
   (off-server destinations are usually a paid add-on on shared hosting).
2. Leave the **databases** ticked, and tick **Email Accounts** and **Databases**
   if offered. Do not deselect them for the sake of a smaller archive — the
   whole point of this step is a complete rollback point.

**Verify — do not skip.** The archive must be (a) present, (b) a plausible size,
(c) not still growing, and (d) containing *both* the database and the site files.
A file that is still being written is not a backup, and a files-only or
db-only archive passes the first three checks while being useless for a bad
update, because the migrations in step 3–4 change the schema.

- The FTP home-directory listing must show a dated `backup-*.tar.gz`.
- Size must be stable across a ~30s re-check. Stable = the job finished.
- Both the database and the site files must be present. See the path warning
  below — `httpfiles/` is **not** where this account's site lives.

> **Path warning.** On this account the site files are under
> `homedir/lylyroseir/`, *not* `httpfiles/`. The archive does contain an
> `httpfiles/` entry, but it is **empty** (a directory with no children), so a
> check that merely greps for `httpfiles/` passes on an archive with no site
> files in it at all. Grep for a file that can only exist if the site was
> actually captured.

Checked this way on 2026-09-27 for the archive created: 389.6 MB, stable, and
containing `mysql/bqwyvowk_lylyrose.sql` **and** `homedir/lylyroseir/wp-config.php`.

> **⚠️ `Backup/fullbackup_to_homedir` is a write, not a query.** Calling it with
> no arguments to "see if the module exists" **starts a real full backup** and
> costs ~410 MB of host disk. It was called twice by mistake while probing 1c on
> 2026-09-27, leaving 3 archives (1.23 GB); the 2 extras were deleted over FTP
> afterwards, restoring the host to the single verified archive. Site content was
> unchanged across the window, so nothing was lost. **Do not use this function to
> test for a function's existence** — check a harmless one first (`Email/list_pops`)
> to confirm the session is authenticated, and treat `Backup/*` as writes.

### Do this — 1a-ii. UpdraftPlus host-local (on-site belt-and-braces)

Worth doing *in addition*, because it is a WordPress-level backup: it knows the
multisite/network tables and the uploads set that matters for the store, and it
restores through the admin UI. It is free, takes a few minutes, and does not
depend on cPanel.

1. WP admin → **UpdraftPlus**. Leave **Backup destinations** empty.
2. **Back up now** (*Database and files*).
3. It writes to `wp-content/updraft/` on the same host.

No destination to connect, no account, no network dependency.

**Verify — do not skip.** The *Existing backups* tab must show a real file with a
size and a date, not a 0-byte entry or an error the UI scrolls past. On the host,
`wp-content/updraft/` must contain dated files, not an empty folder.

### Do this — 1b. Pull a copy off the host (free off-site)

The host is reachable over **passive FTP** — the same channel the 2026-09-24
deploy used, confirmed reachable from this machine. Fetch the account archive
from 1a so the rollback point lives somewhere other than the web host. This is a
~390 MB download; run it and let it finish.

The archive is found in the **login directory** (the account home), not inside
`lylyroseir/`, so there is no `cwd` to get wrong here. FTP login for this account
lands at `/` = `/home3/bqwyvowk`.

Run on **this machine** (reads the git-ignored `.env`):

```bash
python3 - <<'PY'
import ftplib, os, datetime
from dotenv import dotenv_values
cfg = dotenv_values(".env")
dest = "backups/production-" + datetime.date.today().isoformat()
os.makedirs(dest, exist_ok=True)
ftp = ftplib.FTP()
ftp.connect(cfg["PHP_HOST_IP"], 21, timeout=30)
ftp.login(cfg["PHP_HOST_USERNAME"], cfg["PHP_HOST_PASSWORD"])
ftp.set_pasv(True)                      # mandatory: active mode gets 425
try:
    print("login dir:", ftp.pwd())      # '/' == /home3/bqwyvowk
    names = [n for n in ftp.nlst() if n.startswith("backup-") and n.endswith(".tar.gz")]
    if not names:
        raise SystemExit("no backup-*.tar.gz in home dir - run step 1a first")
    newest = max(names)                 # cPanel names sort chronologically
    expected = ftp.size(newest)
    print("pulling:", newest, expected, "bytes")
    local = os.path.join(dest, newest)
    with open(local, "wb") as fh:
        ftp.retrbinary("RETR " + newest, fh.write)
    got = os.path.getsize(local)
    print(f"got {got/1e6:.1f} MB -> {local}")
    if got != expected:
        raise SystemExit(f"TRUNCATED: {got} != {expected}")
    print("size matches host - complete")
finally:
    ftp.quit()
PY
```

Two things to get right, both learned on the 2026-09-24 deploy:

- **Passive mode is mandatory.** Active mode is rejected by the host with
  "425 No data connection".
- **FTP `SIZE` is the check that matters.** The script compares the local file
  size against the host's `SIZE` and fails loudly if they differ. A silent
  truncation is the failure mode that makes people believe they have a backup.

Then confirm the local copy is a *usable* archive, not just the right number of
bytes. Assert on a real site file, not on the `httpfiles/` directory (which is
empty on this account — see the path warning in 1a):

```bash
tar tzf backups/production-*/backup-*.tar.gz \
  | grep -E 'mysql/bqwyvowk_lylyrose\.sql$|homedir/lylyroseir/wp-config\.php$'
```

**Both lines must appear, or the copy is not a usable rollback point:**

- Only the `.sql` → database-only. The schema changes in steps 3–4 are covered but
  the files are not, so a bad plugin/theme update could not be reverted.
- Only `wp-config.php` → files-only. Passes a size check and looks fine, but the
  migrations in steps 3–4 change the schema, so a failed migration could not be
  rolled back. This is the failure this check exists to catch.
- Neither → the pull is broken; re-run 1b.

To eyeball the coverage before trusting it:

```bash
tar tzf backups/production-*/backup-*.tar.gz \
  | grep -c 'homedir/lylyroseir/wp-content/uploads'
```

A non-zero count means product images are captured. The 2026-09-27 archive holds
1,794 upload files.

You can also pull the `lylyroseir/wp-content/updraft/` folder from 1a-ii the same
way (`ftp.cwd("lylyroseir/wp-content/updraft")` then loop `nlst()`), skipping dot
files. It is a small bonus copy, not the rollback point. Updraft splits a backup
into several `*.zip`/`*.gz` parts — pull them all, and keep each backup's parts
together in one dated directory.

**Verify:** local size equals host size (the script enforces this), and the `tar
tzf` listing shows the database. Keep at least the two most recent archives
locally.

### Done 2026-09-27 — 1c. Set the schedule (free)

**⚠️ Verified 2026-09-27 on this host: the cPanel half of 1c does not exist.**
The earlier version of this step told you to set a daily schedule in cPanel →
Backup → Configure. That is not possible on `89.39.208.244` (host
`ircpanel181`). Verified by authenticating a cPanel session and probing the
module:

- The **Backup** module page offers only *Download a Full Account Backup*, *Partial
  Backups*, and restore/upload. There is no schedule control, and no Backup
  Wizard exists at all — `backup/backupwizard`, `backup/wizard` and
  `backup/configure` all return 404.
- The only Backup UAPI function present is `Backup/fullbackup_to_homedir`, which
  runs a backup **now**. There is no `save_schedule` / `get_cron` / `add_cron`
  equivalent.
- **Cron jobs cannot be created either.** The `Cron` UAPI module is not
  installed: every `Cron/*` call fails with `Can't locate Cpanel/API/Cron.pm in
  @INC`. cPanel's *Cron Jobs* page still renders "Add New Cron Job", but its form
  posts to that same missing module — a form that cannot succeed. `Cron/list_cron`
  also fails, which is why the cron job count could not be read.

So there is **no host-side scheduler** to configure. Asking support to install
`Cpanel::API::Cron` or enable Backup scheduling is the only way to get one, and
that is a ticket to the host, not a task you can do.

**What to do instead — UpdraftPlus, which does work and is free:**

**UpdraftPlus → Settings → Backup schedule**: set the automatic interval to
**daily** and confirm a **retention** count is set. Scheduling and basic retention
are free-plugin features. This was the setting left on `manual`, which is the root
cause of "backups exist but none were ever taken".

#### What was actually set, and how (2026-09-27)

Set through a host-side PHP probe (upload a random `_*.php` to the docroot over
FTP, fetch it, delete it) — the same mechanism the 2026-09-26 rebrand used, and
admin-equivalent. The trap is that **the option names are not what you would
guess, and writing the options directly does not schedule anything.**

- The options are `updraft_interval` (files) and `updraft_interval_database`
  (DB). There is **no `updraft_interval_type` and no `updraft_retention`** —
  those names are wrong. Retention is `updraft_retain` / `updraft_retain_db`.
- Scheduling is done by `UpdraftPlus::schedule_backup()` and
  `schedule_backup_database()` ([class-updraftplus.php](../../wordpress/wp-content/plugins/updraftplus/class-updraftplus.php)
  ~line 4779). They are `register_setting()` sanitize callbacks: they
  `wp_schedule_event()` and **return** the interval, and the return value is what
  gets written. Calling them directly schedules the cron but leaves the option
  unset.
- The option write is what triggers them, so **`update_option()` is the correct
  entry point** — it fires `sanitize_option_updraft_interval_database` and the
  schedule appears.

End state verified by reading the cron array:

```
updraft_interval             'daily'   -> updraft_backup            2026-09-27 20:46:33 UTC
updraft_interval_database    'daily'   -> updraft_backup_database   2026-09-27 20:46:33 UTC
updraft_retain               '2'
updraft_retain_db            '2'
updraft_split_every          '2000'
```

Also confirmed on the way: `DISABLE_WP_CRON` is `false`, so WP self-triggers and
the schedule will fire without a host cron job — that is what makes this work at
all given the missing `Cron` module. The first fire is 20:46 UTC = **00:16 local
(Asia/Tehran, +03:30)** on 2026-09-28.

It is worth knowing what you give up: the WordPress-level schedule depends on
WP-Cron firing, so it does not run if the site is down. The cPanel route captured
the database without that dependency. That is the gap left open until the host
enables a scheduler — and it is a gap during the *update pass*, the one time a
broken site is most likely. Mitigation while it stays open: run **1a manually
before each risky change** (it takes a few minutes and is one UAPI call), and
keep the off-host pull from 1b current.

**Verify — 1c mechanism DONE 2026-09-27; first actual fire CONFIRMED 2026-09-28** (see "Confirmed 2026-09-28" below). The table that follows is the 2026-09-27 mechanism check, kept as-is.

Checked at 13:40 UTC on 2026-09-27, via a read-only docroot probe (uploaded
random name, fetched, deleted — the mechanism above). Everything needed for the
schedule to *run* is confirmed on the host, not just written down:

| Check | Result |
| --- | --- |
| `updraft_interval` / `updraft_interval_database` | `'daily'` / `'daily'` — both read back |
| `updraft_backup` cron event | **exists, recurring**, `daily` / 86400s, next 2026-09-27 20:46:33 UTC |
| `updraft_backup_database` cron event | **exists, recurring**, `daily` / 86400s, next 2026-09-27 20:46:33 UTC |
| `DISABLE_WP_CRON` | `false` |
| **Loopback to `wp-cron.php`** | **200, empty body, 0.03 s** |
| `wp-content/updraft/` | only the 3 guard files (`.htaccess`, `index.html`, `web.config`) — no archive yet |
| Updraft backup history | `NULL` — no backup has ever been recorded |
| Site after the probe | `/` `/shop/` `/checkout/` all 200; probe 404 |

The loopback row is the one that matters, and it is not the same fact as
`DISABLE_WP_CRON=false`. That constant only says WP is *permitted* to self-spawn;
the self-request is what actually has to succeed. This host has no cPanel
scheduler, so nothing else will ever fire these events — if the loopback were
blocked, both events would sit registered and never run, looking exactly like
"hasn't fired yet" and staying that way forever. A 200 with an empty body is
WP-Cron's normal success response (it has nothing to return once it has spawned),
so the loopback works.

**Still outstanding (as written 2026-09-27):** the first *real* fire at 20:46 UTC.
After that, confirm a **new dated** archive appeared under `wp-content/updraft/`,
and that `updraft_backup_history` is no longer `NULL`. A schedule that was never
exercised is the same state you are trying to leave behind — the mechanism being
sound is not the same as it having produced a backup.

**Re-checked 2026-09-27 ~17:10 UTC: schedule intact, fire still pending.** Both
events remain at the same fire time with `schedule: daily` / `interval: 86400`,
and no archive or history exists yet — correct, since the fire was ~3h24m away
at that point. A verification is scheduled for 00:16 Tehran time, 2026-09-28.

> **CLOSED 2026-09-28 04:15 UTC — the fire happened and the backup is real.**
> The two paragraphs above are kept as the record of what was being waited for.
> What follows is the confirmation.

### Confirmed 2026-09-28 — first fire produced a verified backup

Six archives appeared in `wp-content/updraft/`, all sharing the run id
`ddea859eb974` and the datestamp `2026-09-28-0415`:

| Archive | Size |
| --- | --- |
| `backup_2026-09-28-0415_Lyly_Rose_ddea859eb974-plugins.zip` | 89,447,010 (85.3 MB) |
| `backup_2026-09-28-0415_Lyly_Rose_ddea859eb974-themes.zip` | 19,692,191 (18.8 MB) |
| `backup_2026-09-28-0415_Lyly_Rose_ddea859eb974-others.zip` | 4,881,649 (4.7 MB) |
| `backup_2026-09-28-0415_Lyly_Rose_ddea859eb974-uploads.zip` | 6,353,289 (6.1 MB) |
| `backup_2026-09-28-0415_Lyly_Rose_ddea859eb974-db.gz` | 416,686 (407 KB) |
| `backup_2026-09-28-0415_Lyly_Rose_ddea859eb974-mu-plugins.zip` | 120 |
| **Total** | **120,790,945 (115.2 MB)** |

Plus `log.ddea859eb974.txt` (108,009 bytes) — UpdraftPlus's own run log, which
was absent before the fire and is itself proof the process ran end to end.

| Check | Result |
| --- | --- |
| `updraft_backup_history` | **no longer `NULL`** — one set, `nonce: ddea859eb974`, `datestamp: 1790568944`, `created_by_version: 1.26.8` |
| Updraft run log | **909 lines, zero error/warning/fatal matches**; ends `The backup succeeded and is now complete` |
| DB archive integrity | gzip decompresses fully (416,686 → 2,673,317 bytes), **36 tables** with `INSERT`, header names `https://lylyrose.ir` and `WordPress Version: 7.1.2` |
| DB archive checksum | sha1 of the downloaded bytes == `4879d6a657a69b595a2ee318518db29847da2703`, matching the sha1 Updraft recorded in history |
| Files are not stubs | `uploads` is **6.35 MB** against a live `uploads/` of **1,792 files / 5.7 MB**; `plugins` is **85.3 MB** against **20,089 files / 240.9 MB** (ratio 0.35, expected for already-compressed assets) |
| Retention ran | log shows the retain pass executing with `retain_files=2, retain_db=2` and retaining the new set |
| Remote destinations | none configured — log line `No remote despatch: user chose no remote backup service`. The set is **host-local only**, which is exactly what 1a-ii (optional) exists to add. |
| Site after | `/` `/shop/` `/checkout/` all 200; both probes 404 after deletion |

**Re-verified 2026-09-28 06:55 UTC, after the fire.** Both events are still
registered and still `schedule: daily` / `interval: 86400`, and both have
**moved off the stamp they fired on**: next run `1790628393` =
`2026-09-29 04:33:13 UTC` (00:33:33 Tehran), i.e. ~24h after the 04:15 run
rather than the original `2026-09-27 20:46:33 UTC`. This is the one part of the
schedule that a "still registered" check *cannot* prove — an event looks
identical before a fire, after a fire, and if it never fires — so the reschedule
is the piece that shows the daily cycle actually turned over.
`updraft_backup_history` still holds exactly one set, `updraft_retain` and
`updraft_retain_db` are both `2`, and no remote target is configured
(`updraft_rmode` empty). Site healthy: `/` `/shop/` `/checkout/` `/my-account/`
all 200.

**Disk ceiling this creates.** `retain_files=2` / `retain_db=2` means Updraft
keeps **2 file sets and 2 DB sets** on the host. At the current 115.2 MB per set
that is a **steady-state ~231 MB** in `wp-content/updraft/`, not unbounded
growth — but it is on the same host as the site it protects, and the host's
quota is unknown (see the `fullbackup_to_homedir` note above, which left 1.23 GB
on disk). Retention is the only thing standing between this and a full disk, so
do not raise it without raising the awareness of what that costs.

**How it was read:** FTP `nlst` + `SIZE` + `MDTM` on `wp-content/updraft`
(passive mode), plus two read-only docroot probes uploaded with a random leading
underscore, fetched over HTTPS, and deleted — each confirmed gone by *both* a
404 and an FTP listing. No backup was triggered by hand at any point.

**The fire was late by design, not by fault.** The event was stamped
`2026-09-27 20:46:33 UTC` and the archives are stamped `04:15` — 7h29m later.
That is the behaviour documented above this section: WP-Cron spawns on a
front-end request, and this store is low-traffic, so the run waited for a
visitor. The docroot probe that read the cron state at 04:15 was itself a PHP
request, and it is the request that released it. **A missing archive on a
low-traffic site is not evidence of a broken schedule** — this is the case the
2026-09-27 "past-due is not broken" note was written for, and it is the reason
the check had to be a *file that appeared on its own* rather than a re-read of
the schedule.

**If that check finds no archive, do not re-run the schedule writer.** First
establish whether the site has had *any* front-end request since 20:46 UTC:
WP-Cron only spawns on a page view, so with no traffic the events sit past-due
without being broken. `DISABLE_WP_CRON=false` and a working loopback are
necessary but not sufficient — neither produces a request on its own.

**Do not mistake past-due events for a broken cron (verified 2026-09-27).** At
~17:51 UTC, four events read overdue — `action_scheduler_run_queue` by 26
minutes. One page view rescheduled all four instantly, putting that 60-second
event 36 s out. This store is low-traffic, so between visits every short-interval
event is *expected* to read past-due. The test is therefore: **fetch a page,
then re-read.** Rescheduled ⇒ healthy. Still overdue after a page view ⇒ broken.

**Reading the cron option (cost two probe rounds — get this right).** WP stores
`cron` keyed by **timestamp, not hook name**:

```php
$cron = _get_cron_array();
$e = $cron[1790541993]['updraft_backup']['40cd750bba9870f18aada2478b24840a'];
$e['schedule'];  // 'daily'   -- 86400 in $e['interval']
```

`$cron['updraft_backup']` returns nothing, and an event's `schedule` key sits one
level *below* the hook, under `md5(serialize($args))`. Reading either wrongly
reports a perfectly healthy schedule as deleted. Also `gmdate('…', $ts)` throws a
`TypeError` on the strings PHP hands back from the serialized array — cast to
`(int)` first.

Two probes were burned getting here, both my error and both worth recording:
`gmdate()` on `$event['schedule']` (which is the recurrence *name*, `'daily'`, not
a timestamp — WP's cron array stores the interval separately), and a guessed
`UpdraftPlus::get_updraft_backup_history()` that does not exist (the real reader
is `UpdraftPlus_Options::get_updraft_option('updraft_backup_history')`, and it
must go through that accessor because the history option is filtered). Both
threw fatally on the live store before being fixed. It is the same lesson as the
`updraft_interval_type` write in 1c: **a guessed API name fails loudly here, but
in a write path it would have failed silently** — the write that matters is the
one to double-check.


### Optional — only if you want a true off-site safety net

The free plugin also backs up to Google Drive / Dropbox / S3 / FTP with no
subscription. They are free, but the host is in Iran and access to those services
from an Iranian IP is restricted in practice, so a backup there can fail or hang.
If you try one, watch the log for a real success and confirm a retrievable file —
do not trust a green checkbox. UpdraftVault storage is a separate paid
subscription (~5 GB for the first month at $1; 1 GB included with paid plans); a
full production backup is roughly 400–500 MB, so a 1 GB tier holds about two.

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

**Done 2026-09-27.** The version lines in `docs/CONTINUATION.md` and this page were
rewritten **from the live site**, not from local. The `CONTINUATION.md` audit at the
top now carries a 2026-09-27 entry with the verified numbers, and the 2026-09-26
entry is kept but explicitly marked superseded.

Worth recording *how* the live numbers were obtained, since there is no SSH and no
wp-cli on the host: a **read-only PHP probe uploaded into the docroot under a
random name, fetched over HTTPS, then deleted**. That is the same mechanism the
2026-09-26 rebrand used (`DEVELOPMENT_LOG.md`). Two rules learned the hard way:

- **Use a random filename, and delete the probe immediately after fetching.** A
  predictable name in the docroot of a live store is a liability.
- **Do not trust one signal.** The probe said 0 pending; the plugins' own
  `Version:` headers, pulled over FTP, agreed. Two independent reads, or it is not
  verified.

**The lesson generalises, and it is now the fourth time:** locally-green /
docs-current state hid a live-site problem. The earlier three were the
`$table_prefix` and `.htaccess` omissions that looked like a failed DB import, a
`git push` that reported "Everything up-to-date" on a failed push, and this one —
docs claiming 23 pending updates when the live site had **0** and had for hours.
**Read state off the live site; never infer it from this file or from local.**

---

## What is deliberately still open after all this

**The update pass is no longer on this list — it is done.** What remains:

- ~~**1c's first real fire**~~ — **CLOSED 2026-09-28.** Superseded text kept
  above for the record: *"the schedule's mechanism is verified (both events
  recurring, loopback to `wp-cron.php` confirmed 200), but it has not yet produced
  a backup…"* The fire happened at 04:15 UTC on 2026-09-28 and produced a
  complete 115.2 MB set, `updraft_backup_history` is no longer `NULL`, and the DB
  archive matches its recorded sha1. **1c is closed.** What remains of 1c is only
  1a-ii (a remote copy), which is optional and now the sole backup gap — the
  daily schedule now writes host-local, so keep 1b (the off-host pull) current
  and keep 1a manual before risky changes.
- **ZarinPal live merchant code** and **`sandbox: no`** — not yet acquired, but
  **corrected 2026-09-27**: the gateway is **enabled and live at checkout**, not
  disabled. A real checkout POST created order 1809 and redirected to
  `sandbox.zarinpal.com` with a real authority, using the all-zero dummy
  merchant. So production creates real orders and sends customers to a payment
  page it cannot collect money from. The store is in **demo mode** (hardcoded
  banner in `header.php`), so this is pre-launch state rather than a live-money
  bug — but the banner is a statement to visitors, not a control. **Before
  launch: a real merchant code with `sandbox: no`, or disable the gateway.**
  Details in `CONTINUATION.md`.
- **PWSMS real credentials** — the `Logger` sink makes production SMS a **silent
  no-op**, which also means the P0 mobile OTP login feature does not work on the live
  site. It is worth treating this as higher priority than it looks.
- **WP Mail SMTP credentials** — the `wp_mail_smtp` option is **empty** on the
  live site: no provider, no from-address, no SMTP host or key. Unconfigured,
  not merely unverified.
- **WP Super Cache** — `WP_CACHE` is `false`, there is no `advanced-cache.php`
  and no `supercache` directory, so caching is **off**, not misconfigured.
  Needs enabling.
- **Wordfence** — the plugin is **inactive** and its `wordfence` option is
  empty. It needs activating and configuring, not just a scan.
- ~~**`WP_REDIS_*` defines**~~ — **already resolved.** Verified 2026-09-27:
  there are no `WP_REDIS_*` defines, no `object-cache.php` drop-in, and the
  plugin is deactivated, so `WP_Object_Cache` is the live backend. Nothing to
  drop; this item can be closed.
