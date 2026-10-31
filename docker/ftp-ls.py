#!/usr/bin/env python3
"""List a remote directory over FTP, unfiltered, with MLSD (type+size+mtime).

Why: a bare 404 or 550 is not proof of absence — it is also what a path or
permissions error returns. Enumerate the parent and grep, and prefer MLSD
(LIST can hide names on an empty dir).

Usage: python3 docker/ftp-ls.py wp-content/uploads/2026/10 [grep-substring]
"""
import sys
from pathlib import Path

from dotenv import dotenv_values
from ftplib import FTP

ROOT = Path(__file__).resolve().parent.parent
cfg = dotenv_values(ROOT / ".env")

rel = sys.argv[1] if len(sys.argv) > 1 else "."
needle = sys.argv[2] if len(sys.argv) > 2 else None

ftp = FTP()
ftp.connect(cfg["PHP_HOST_IP"], 21)
ftp.login(cfg["PHP_HOST_USERNAME"], cfg["PHP_HOST_PASSWORD"])
ftp.set_pasv(True)
ftp.cwd("/")

target = "/%s/%s" % (cfg["LYLYROSE_FOLDER"], rel.strip("/"))
ftp.cwd(target)
print("cwd ->", target)

rows = []
# ftplib's mlsd is a generator; collect it.
entries = list(ftp.mlsd("", ["type", "size", "modify"]))
if not entries:
    print("!!! EMPTY MLSD — suspect (passiveserver can drift); try again")
if needle:
    entries = [e for e in entries if needle in e[0]]
print(f"{len(entries)} entr(ies)" + (f" matching {needle!r}" if needle else ""))
for name, facts in sorted(entries):
    print("  %-40s %-5s %10s  %s" % (name, facts.get("type", "?"),
                                     facts.get("size", "-"), facts.get("modify", "-")))
ftp.quit()
