#!/usr/bin/env python3
"""Pull one file from the live host, by docroot-relative path. Read-only.

Backs up a file before a targeted deploy overwrites it, and reads it back after
so the upload can be hash-verified rather than size-verified (a same-length edit
reads as identical under a size check).

Usage: python3 docker/ftp-pull-one.py wp-content/themes/lylyrose/functions.php [out]
"""
import hashlib
import sys
from pathlib import Path

from dotenv import dotenv_values
from ftplib import FTP

ROOT = Path(__file__).resolve().parent.parent
cfg = dotenv_values(ROOT / ".env")

rel = sys.argv[1]
out = Path(sys.argv[2]) if len(sys.argv) > 2 else Path("/tmp") / Path(rel).name

ftp = FTP()
ftp.connect(cfg["PHP_HOST_IP"], 21)
ftp.login(cfg["PHP_HOST_USERNAME"], cfg["PHP_HOST_PASSWORD"])
ftp.set_pasv(True)

buf = bytearray()
ftp.retrbinary("RETR /%s/%s" % (cfg["LYLYROSE_FOLDER"], rel), buf.extend)
ftp.quit()

out.write_bytes(buf)
print("wrote %s (%d bytes)" % (out, len(buf)))
print("sha256 %s" % hashlib.sha256(buf).hexdigest())
