#!/usr/bin/env python3
"""Compare first-party files on the live host against local, by size.

Read-only. A green suite says the local code is right; it says nothing about what
the host is actually serving. Only a byte-level look tells you the deploy gap.
"""
import ftplib
import os
import sys
from pathlib import Path

from dotenv import dotenv_values

ROOT = Path(__file__).resolve().parent.parent
cfg = dotenv_values(ROOT / ".env")
SRC = ROOT / "wordpress"

TREES = [
    "wp-content/plugins/lylyrose-core",
    "wp-content/themes/lylyrose",
]
SKIP_DIRS = {"__pycache__", ".git", "node_modules"}
SKIP_EXT = {".pyc", ".log", ".sql", ".gz", ".bak", ".orig", ".rej"}


def local_files():
    out = {}
    for tree in TREES:
        for dirpath, dirnames, filenames in os.walk(SRC / tree):
            dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
            for name in filenames:
                p = Path(dirpath) / name
                if p.suffix.lower() in SKIP_EXT:
                    continue
                out[str(p.relative_to(SRC))] = p.stat().st_size
    return out


def main():
    local = local_files()

    # One SIZE per file with a fresh cwd walk each is thousands of round-trips and
    # the server drops the control connection partway through (EOFError). Group by
    # directory instead: one cwd per directory, then one SIZE per file in it.
    by_dir = {}
    for rel, size in local.items():
        by_dir.setdefault(str(Path(rel).parent), []).append((Path(rel).name, size, rel))

    diff, missing, same = [], [], 0
    c = ftplib.FTP(cfg["PHP_HOST_IP"])
    c.login(cfg["PHP_HOST_USERNAME"], cfg["PHP_HOST_PASSWORD"])
    c.set_pasv(True)
    try:
        for d in sorted(by_dir):
            try:
                c.cwd(f"/{cfg['LYLYROSE_FOLDER']}/{d}")
            except ftplib.error_perm:
                for name, _size, rel in by_dir[d]:
                    missing.append(rel)
                continue
            for name, size, rel in by_dir[d]:
                try:
                    remote_size = c.size(name)
                except Exception:
                    missing.append(rel)
                    continue
                if remote_size != size:
                    diff.append((rel, remote_size, size))
                else:
                    same += 1
            c.cwd("/")
    finally:
        try:
            c.quit()
        except Exception:
            pass

    print(f"identical: {same}   differing: {len(diff)}   absent: {len(missing)}\n")
    if diff:
        print("DIFFERENT (host -> local):")
        for rel, r, l in diff:
            print(f"  {rel}: {r:,} -> {l:,}  ({l - r:+,})")
    if missing:
        print("\nABSENT ON HOST:")
        for rel in missing:
            print(f"  {rel}")


if __name__ == "__main__":
    sys.exit(main())