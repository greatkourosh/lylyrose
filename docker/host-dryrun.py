#!/usr/bin/env python3
"""Upload a multi-file read-only probe to the live host, fetch it, delete every part.

live-probe.py handles a single self-contained file. This one is for a probe that
also reads a data file it must be given. Nothing here can write: the entry script
is expected to be read-only, and the caller chooses it.

    python3 docker/host-dryrun.py probe-validate-finder.php finder-profiles.json
"""
import ftplib, os, secrets, ssl, subprocess, sys, urllib.request
from dotenv import dotenv_values

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
cfg = dotenv_values(os.path.join(ROOT, '.env'))
ENTRY, DATA = sys.argv[1], sys.argv[2]

def fetch(url):
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    try:
        with urllib.request.urlopen(url, context=ctx, timeout=900) as r:
            return r.read().decode('utf-8', 'replace')
    except Exception as e:
        return 'FETCH ERROR %s' % e

tag = '_' + secrets.token_hex(8)
names = {ENTRY: tag + '_e_' + os.path.basename(ENTRY),
         DATA:  tag + '_d_' + os.path.basename(DATA)}

for src, name in names.items():
    subprocess.run(['docker', 'cp', src, 'lylyrose-wp:/tmp/' + name], check=True)
    r = subprocess.run(['docker', 'exec', 'lylyrose-wp', 'php', '-l', '/tmp/' + name],
                       capture_output=True)
    if b'No syntax errors' not in r.stdout:
        sys.exit('syntax error in %s: %s' % (src, r.stdout.decode()))

c = ftplib.FTP(cfg['PHP_HOST_IP'])
c.login(cfg['PHP_HOST_USERNAME'], cfg['PHP_HOST_PASSWORD'])
c.set_pasv(True)                 # mandatory: Pure-FTPd rejects active mode
c.cwd(cfg['LYLYROSE_FOLDER'])
try:
    for src, name in names.items():
        with open(src, 'rb') as fh:
            c.storbinary('STOR ' + name, fh)

    print(fetch('https://lylyrose.ir/' + names[ENTRY]))

    for name in names.values():
        c.delete(name)
    listed = [x for x in c.nlst() if x.startswith(tag)]
finally:
    c.quit()
print('--- uploaded 2 files under %s: still listed=%s' % (tag, listed or 'no'))