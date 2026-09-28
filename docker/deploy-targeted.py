#!/usr/bin/env python3
"""
Upload first-party code to a cPanel host, and nothing else.

Why this exists
---------------
The documented deploy is "merge master -> hosting-ready, upload changed files",
but the vendor plugins committed on that branch are pinned to older versions
than the ones production actually runs (WooCommerce 11.0.1 there vs 11.1.0
live). Following the merge wholesale therefore ships an unreviewed plugin
update wave along with whatever feature was meant to go out, and the two
cannot be told apart afterwards.

So: never merge into a deploy branch to ship a feature. Upload only the paths
you changed. This script takes that list explicitly and refuses to walk a
directory tree, so vendor code cannot be dragged along by accident.

Reads FTP credentials from .env, uploads over passive FTP (Pure-FTPd on these
hosts rejects active mode), and verifies every file's size after the write.

Usage
-----
    # list what would go up, touch nothing
    python3 docker/deploy-targeted.py --dry-run

    # ship one feature
    python3 docker/deploy-targeted.py wp-content/plugins/aroma-store-core
    python3 docker/deploy-targeted.py wp-content/plugins/aroma-store-core/includes/class-gift-cards.php
"""

import argparse
import fnmatch
import os
import sys
from pathlib import Path

try:
    from ftplib import FTP
except ImportError:  # pragma: no cover
    sys.exit("needs python3-ftplib (Debian: python3-ftplib)")

try:
    from dotenv import dotenv_values
except ImportError:  # pragma: no cover
    sys.exit("needs python3-dotenv (Debian: python3-dotenv)")

# Vendor code. Uploading any of this is a plugin update, not a feature deploy,
# and needs its own review + smoke check, so it is refused by name.
VENDOR = (
    "wp-content/plugins/woocommerce",
    "wp-content/plugins/dokan-lite",
    "wp-content/plugins/woo-wallet",
    "wp-content/plugins/woo-cart-abandonment-recovery",
    "wp-content/plugins/redis-cache",
    "wp-content/plugins/wordfence",
    "wp-content/plugins/wordpress-seo",
    "wp-content/plugins/google-site-kit",
    "wp-content/plugins/microsoft-clarity",
    "wp-content/plugins/wp-super-cache",
    "wp-content/plugins/updraftplus",
    "wp-content/themes/twentytwentyfive",
)

SKIP_DIRS = {"__pycache__", ".git", "node_modules", ".idea", ".vscode"}
SKIP_EXT = {".pyc", ".log", ".sql", ".gz", ".bak", ".orig", ".rej"}


def is_vendor(rel: str) -> bool:
    return any(rel == v or rel.startswith(v + "/") for v in VENDOR)


def collect(src_root: Path, targets: list[str]) -> list[tuple[Path, str]]:
    """Resolve target paths to (local, relative-to-src_root) pairs."""
    src_root = src_root.resolve()
    out = []
    for target in targets:
        base = (src_root / target).resolve()
        if not str(base).startswith(str(src_root)):
            sys.exit(f"refusing path outside source root: {target}")
        if base.is_file():
            out.append((base, str(base.relative_to(src_root))))
        elif base.is_dir():
            for dirpath, dirnames, filenames in os.walk(base):
                dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
                for name in filenames:
                    if Path(name).suffix.lower() in SKIP_EXT:
                        continue
                    f = Path(dirpath) / name
                    out.append((f, str(f.relative_to(src_root))))
        else:
            sys.exit(f"no such path: {target}")
    # Dedup: a file given directly and via its parent dir.
    seen, uniq = set(), []
    for local, rel in sorted(out, key=lambda p: p[1]):
        if rel in seen:
            continue
        seen.add(rel)
        uniq.append((local, rel))
    return uniq


def main() -> int:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("targets", nargs="*", help="paths under --src to upload (file or directory)")
    p.add_argument("--src", default="wordpress", help="local source root (default: wordpress)")
    p.add_argument("--remote", default=None, help="remote dir under the docroot; default: $LYLYROSE_FOLDER from .env")
    p.add_argument("--env", default=".env")
    p.add_argument("--dry-run", action="store_true", help="list the files, upload nothing")
    args = p.parse_args()

    if not args.targets:
        p.error("name at least one path to upload, or pass --dry-run to list the first-party tree")

    src_root = Path(args.src)
    if not src_root.is_dir():
        sys.exit(f"source root not found: {src_root}")

    files = collect(src_root, args.targets)
    vendor = [rel for _, rel in files if is_vendor(rel)]
    if vendor:
        sys.exit(
            "refusing to upload vendor code as part of a feature deploy:\n  "
            + "\n  ".join(vendor[:10])
            + "\nVendor updates need their own review and smoke check; see CONTINUATION.md."
        )
    if not files:
        sys.exit("nothing to upload")

    total = sum(f.stat().st_size for f, _ in files)
    print(f"{len(files)} file(s), {total:,} bytes")
    for _, rel in files:
        print(f"  {rel}")
    if args.dry_run:
        print("\ndry run: nothing uploaded")
        return 0

    cfg = dotenv_values(args.env)
    host = cfg.get("PHP_HOST_IP")
    user = cfg.get("PHP_HOST_USERNAME")
    pw = cfg.get("PHP_HOST_PASSWORD")
    docroot = args.remote or cfg.get("LYLYROSE_FOLDER")
    if not all((host, user, pw, docroot)):
        sys.exit(f"{args.env} is missing PHP_HOST_IP / PHP_HOST_USERNAME / PHP_HOST_PASSWORD / LYLYROSE_FOLDER")

    ftp = FTP()
    ftp.connect(host, 21)
    ftp.login(user, pw)
    ftp.set_pasv(True)  # mandatory: these hosts reject active mode
    print(f"\nlogged in to {host}, docroot {docroot}")

    def ensure_dir(remote_dir: str) -> None:
        """Walk down from the login root, creating each level.

        Absolute paths only. An earlier version built a relative path and
        re-walked it per segment, which re-descended the directory it had just
        created and nested docroot-relative junk four levels deep inside the
        docroot. Same for `ftp.cwd`: it is relative to the current directory, so
        a loop that cds into a dir and then cds the full path again descends
        twice.
        """
        ftp.cwd("/")
        walked = ""
        for part in [x for x in remote_dir.split("/") if x]:
            walked = f"{walked}/{part}"
            try:
                ftp.cwd(walked)
            except Exception:
                ftp.mkd(walked)
                ftp.cwd(walked)

    failures = []
    for local, rel in files:
        remote = f"/{docroot}/{rel}"
        remote_dir = os.path.dirname(remote)
        try:
            ensure_dir(remote_dir)
        except Exception as exc:
            failures.append(f"{rel}: mkdir {remote_dir}: {exc}")
            continue
        # ensure_dir() leaves the session inside the target directory, so the
        # write is a bare basename. A partial transfer would leave the remote in
        # a state the next run cannot detect, so the size is read before and
        # after every write.
        name = os.path.basename(rel)
        try:
            before = ftp.size(name)
        except Exception:
            before = None
        try:
            with open(local, "rb") as fh:
                ftp.storbinary("STOR " + name, fh)
            want = local.stat().st_size
            got = ftp.size(name)
            if got != want:
                failures.append(f"{rel}: size {got} != local {want} (was {before})")
            else:
                print(f"  ok  {rel} ({got:,} B, was {before})")
        except Exception as exc:
            failures.append(f"{rel}: {exc}")

    ftp.quit()

    if failures:
        print("\nFAILED:")
        for f in failures:
            print("  -", f)
        return 1
    print(f"\n{len(files)} file(s) uploaded and size-verified")
    return 0


if __name__ == "__main__":
    sys.exit(main())
