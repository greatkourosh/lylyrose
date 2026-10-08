#!/usr/bin/env python3
"""Report whether the newest UpdraftPlus run on the live host has been pulled off-host.

Read-only: lists the host and the local backup dirs, pulls nothing. The pull
stays a manual, deliberate step (PRODUCTION_UPDATE_RUNBOOK.md §1b) because it
moves real customer data onto this machine.

The daily schedule is healthy and has always been. What fails is the *check*:
five separate occasions a run fired and nobody noticed, so gaps reached eight
days before being caught. Run this weekly and it cannot recur.

Exit 0 = newest run is pulled and complete. Exit 1 = stale, with what to pull.
"""
import ftplib
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))  # local dotenv shim

from dotenv import dotenv_values

ROOT = Path(__file__).resolve().parent.parent
cfg = dotenv_values(ROOT / ".env")
BACKUPS = ROOT / "backups"
UPDRAFT = cfg["LYLYROSE_FOLDER"] + "/wp-content/updraft"

# A run is only a usable rollback point if the DB and the files came with it.
# db alone covers schema but not a bad theme upload; themes alone covers neither.
REQUIRED = ("-db.gz", "-themes.zip", "-uploads.zip")
SKIP = (".", "log.", "index.html", "web.config")


def run_id(filename):
    """Updraft names every part <date>_<time>_<Site>_<runid>-<what>.<ext>."""
    return filename.rsplit("_", 1)[-1].split("-", 1)[0].split(".")[0]


def host_runs():
    """Return {run_id: {"parts": {name: size}, "mtime": str}} for every run.

    mlsd, not nlst: it is server-filterable and carries per-file mtimes, so runs
    group by id and order by real time without parsing filenames.
    """
    ftp = ftplib.FTP()
    ftp.connect(cfg["PHP_HOST_IP"], 21, timeout=30)
    ftp.login(cfg["PHP_HOST_USERNAME"], cfg["PHP_HOST_PASSWORD"])
    ftp.set_pasv(True)  # mandatory: active mode gets 425
    try:
        ftp.cwd(UPDRAFT)
        runs = {}
        for name, facts in ftp.mlsd():
            if name.startswith(SKIP) or not name.endswith((".zip", ".gz")):
                continue
            r = runs.setdefault(run_id(name), {"parts": {}, "mtime": ""})
            r["parts"][name] = int(facts.get("size", 0))
            r["mtime"] = max(r["mtime"], facts.get("modify", ""))
        return runs
    finally:
        ftp.quit()


def pulled_runs():
    """Return {run_id: local_dir} for runs already pulled into backups/."""
    if not BACKUPS.is_dir():
        return {}
    out = {}
    for d in BACKUPS.glob("updraft-*"):
        for f in d.iterdir():
            if not f.name.startswith(SKIP) and f.name.endswith((".zip", ".gz")):
                out[run_id(f.name)] = d
    return out


def main():
    host = host_runs()
    local = pulled_runs()
    if not host:
        print("FAIL: no runs on the host — updraft dir is empty or has moved")
        return 1

    print(f"runs on host: {len(host)}   pulled locally: {len(local)}\n")
    for rid in sorted(host, key=lambda r: host[r]["mtime"]):
        mark = "pulled" if rid in local else "NOT PULLED"
        print(f"  {host[rid]['mtime']}  {rid}  "
              f"{len(host[rid]['parts'])} parts  {mark}")

    # Newest by mtime: it is the only ordering that does not depend on file sizes
    # happening to grow, which they need not.
    newest = max(host, key=lambda r: host[r]["mtime"])
    parts, d = host[newest]["parts"], local.get(newest)

    print(f"\nnewest run: {newest} ({host[newest]['mtime']} UTC)")
    if not d:
        print("  PULLED: no")
        print(f"\nSTALE — pull run {newest}; command in §1b of "
              f"PRODUCTION_UPDATE_RUNBOOK.md")
        return 1

    short = [n for n, size in parts.items()
             if not (d / n).exists() or (d / n).stat().st_size != size]
    try:
        shown = d.relative_to(ROOT)
    except ValueError:
        shown = d          # BACKUPS can point outside the repo in a test harness
    print(f"  PULLED:  {shown}")
    print(f"  complete: {not short}" + (f"  short/mismatched: {', '.join(short)}"
                                        if short else ""))
    missing = [s for s in REQUIRED if not any(s in n for n in parts)]
    if missing:
        print(f"  NOTE: no {', '.join(missing)} part")

    if short:
        print("\nSTALE — re-pull: a size mismatch is the failure mode that makes "
              "people believe they have a backup")
        return 1

    print("\nOK — newest run is pulled and complete.")
    print("Reminder: a correct size is not a current state. Confirm the archived")
    print("style.css Version: matches what production serves.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
