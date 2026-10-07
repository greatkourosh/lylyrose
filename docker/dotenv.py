"""Minimal stand-in for python-dotenv's dotenv_values.

PyPI is unreachable from this host, and dotenv_values is only ever used here to
read KEY=VALUE out of .env. Deliberately narrow: no interpolation, no quoting
beyond stripping matched quotes, no export prefix handling.
"""

def dotenv_values(path):
    out = {}
    with open(path, encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, val = line.partition("=")
            key = key.strip()
            if key.startswith("export "):
                key = key[len("export "):].strip()
            val = val.strip()
            if len(val) >= 2 and val[0] == val[-1] and val[0] in "\"'":
                val = val[1:-1]
            out[key] = val
    return out