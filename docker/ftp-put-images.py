#!/usr/bin/env python3
"""Upload the hero banner images to the live docroot, size-verified.

Binary-safe (set_pasv + STOR, no cwd juggling: an earlier uploader cwd'ed into
dirs and then stored absolute paths, and every file after the first 553'd).

Usage: python3 docker/ftp-put-images.py
"""
import os
from pathlib import Path

from dotenv import dotenv_values
from ftplib import FTP

ROOT = Path(__file__).resolve().parent.parent
cfg = dotenv_values(ROOT / ".env")

DOCROOT = cfg["LYLYROSE_FOLDER"]
REL = "wp-content/uploads/2026/10"
LOCAL = ROOT / "wordpress" / REL
FILES = ["hero-banner-clean.webp", "hero-banner-clean.avif"]

ftp = FTP()
ftp.connect(cfg["PHP_HOST_IP"], 21)
ftp.login(cfg["PHP_HOST_USERNAME"], cfg["PHP_HOST_PASSWORD"])
ftp.set_pasv(True)

ftp.cwd("/%s/%s" % (DOCROOT, REL))
print("cwd ->", "/%s/%s" % (DOCROOT, REL))

fail = 0
for name in FILES:
    src = LOCAL / name
    want = src.stat().st_size
    try:
        before = ftp.size(name)
    except Exception:
        before = None
    with open(src, "rb") as fh:
        # bare basename: the session is already inside the target directory
        ftp.storbinary("STOR " + name, fh)
    got = ftp.size(name)
    ok = got == want
    fail += 0 if ok else 1
    print("  %s  %-26s %8d B (was %s)" % ("ok " if ok else "BAD", name, got, before))

ftp.quit()
print("FAILED" if fail else "all uploaded")
raise SystemExit(1 if fail else 0)
