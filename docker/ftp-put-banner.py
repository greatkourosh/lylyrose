#!/usr/bin/env python3
"""Replace the live hero banner bytes with the text-free version.

Live attachment 1937 ("Hero slideshow banner") points at 2026/10/hero-banner.webp
and all five hero slides reference 1937. Its stored metadata is empty and both
the old and the clean files are 2000x403, so overwriting the bytes keeps every
definition and every size truthful -- no DB change and no re-pick needed.

Also refreshes the .avif sibling, which the AVIF swap prefers when the request
offers image/avif.

Usage: python3 docker/ftp-put-banner.py [--restore]
"""
import sys
from pathlib import Path

from dotenv import dotenv_values
from ftplib import FTP

ROOT = Path(__file__).resolve().parent.parent
cfg = dotenv_values(ROOT / ".env")
REL = "wp-content/uploads/2026/10"

if "--restore" in sys.argv:
    SRC = ROOT / "backups/pre-hero-banner-clean"
    PAIRS = [("hero-banner.webp.live", "hero-banner.webp"),
             ("hero-banner.avif.live", "hero-banner.avif")]
    print("RESTORING from backups/pre-hero-banner-clean")
else:
    SRC = ROOT / "wordpress" / REL
    PAIRS = [("hero-banner-clean.webp", "hero-banner.webp"),
             ("hero-banner-clean.avif", "hero-banner.avif")]
    print("APPLYING the clean banner")

ftp = FTP()
ftp.connect(cfg["PHP_HOST_IP"], 21)
ftp.login(cfg["PHP_HOST_USERNAME"], cfg["PHP_HOST_PASSWORD"])
ftp.set_pasv(True)
ftp.cwd("/%s/%s" % (cfg["LYLYROSE_FOLDER"], REL))
print("cwd ->", "/%s/%s" % (cfg["LYLYROSE_FOLDER"], REL))

fail = 0
for local_name, remote_name in PAIRS:
    src = SRC / local_name
    want = src.stat().st_size
    before = ftp.size(remote_name)
    with open(src, "rb") as fh:
        ftp.storbinary("STOR " + remote_name, fh)
    got = ftp.size(remote_name)
    ok = got == want
    fail += 0 if ok else 1
    print("  %s  %-18s %8d B (was %d)" % ("ok " if ok else "BAD", remote_name, got, before))

ftp.quit()
print("FAILED" if fail else "banner replaced")
raise SystemExit(1 if fail else 0)
