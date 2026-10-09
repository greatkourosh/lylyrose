#!/usr/bin/env python3
"""Pull one UpdraftPlus run off the live host, size-verified per part.

Manual step, per PRODUCTION_UPDATE_RUNBOOK.md 1b: it moves real customer and
order data onto this machine, so it is never folded into backup-check.py.

    python3 docker/_pull-updraft.py 90bb68c3ee88
"""
import ftplib
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from dotenv import dotenv_values

ROOT = Path(__file__).resolve().parent.parent
cfg = dotenv_values(ROOT / ".env")
run = sys.argv[1]
dest = ROOT / "backups" / ("updraft-" + run.rsplit("_", 1)[-1])
dest.mkdir(parents=True, exist_ok=True)

ftp = ftplib.FTP()
ftp.connect(cfg["PHP_HOST_IP"], 21, timeout=60)
ftp.login(cfg["PHP_HOST_USERNAME"], cfg["PHP_HOST_PASSWORD"])
ftp.set_pasv(True)  # active mode is rejected with 425
try:
    ftp.cwd(cfg["LYLYROSE_FOLDER"] + "/wp-content/updraft")
    parts = [n for n in ftp.nlst() if run in n and not n.startswith((".", "log."))
             and not n.endswith(("index.html", "web.config"))]
    if not parts:
        raise SystemExit(f"no parts for run {run}")
    for name in sorted(parts):
        expected = ftp.size(name)
        local = dest / name
        with open(local, "wb") as fh:
            ftp.retrbinary("RETR " + name, fh.write)
        got = os.path.getsize(local)
        if got != expected:
            raise SystemExit(f"TRUNCATED {name}: {got} != {expected}")
        print(f"{name}  {got} == {expected}  OK")
finally:
    ftp.quit()
print("pulled to", dest)