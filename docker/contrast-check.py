#!/usr/bin/env python3
"""Contrast checker for the theme palettes, and the tool that derives a Night palette.

Both existing palettes document their contrast ratios by hand in the :root
comment. lylyrose-navy/style.css went further and claimed "every value here is
verified by docker/contrast-check.py, which asserts >=4.5:1" -- but that file did
not exist. This is it.

Two jobs:

  report   print every palette's contrast table and exit non-zero if anything
           falls below the threshold, so it can be wired into the suite
  derive   given a set of surface/text colours, lighten or darken a hue until it
           clears the ratio -- how the Night palette below was produced, rather
           than hand-picked and hoped for

WCAG 2.1 relative luminance and contrast ratio, unmodified.
"""
import argparse
import json
import os
import sys

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# The pairs that actually have to be readable on each surface. A palette is not
# "accessible" in the abstract; it is these specific combinations.
# (label, foreground token, background token, minimum ratio)
#
# Borders are reported but not gated. WCAG 1.4.11 only requires 3:1 for a
# boundary that is the sole means of identifying a control, and a decorative
# hairline between cards needs nothing; asserting a flat minimum on it produced
# two failures on shipped palettes that are otherwise correct, which would train
# whoever runs this to ignore it.
PAIRS = [
    ('body text on background',   'text',      'bg',     4.5),
    ('muted on background',       'muted',     'bg',     4.5),
    ('accent text on background', 'red',       'bg',     4.5),
    ('gold on background',        'teal',      'bg',     4.5),
    ('heading on background',     'ink',       'bg',     3.0),
    ('white on accent',           'white',     'red',    4.5),
    ('white on gold',             'white',     'teal',    3.0),
    ('badge on background',       'badge-red', 'bg',     4.5),
]

# A dark palette inverts the button: its accent is light, so the label on it must
# be dark, and "white on accent" is not a pair that occurs. It is gated on the
# presence of on-accent rather than checked and ignored, because reporting a
# FAIL for a pair the palette never uses is noise that trains people to skip the
# report. Gate the light pairs on their absence.
PAIRS_DARK = [
    ('button label on accent',    'on-accent', 'red',    4.5),
    ('button label on gold',      'on-gold',   'teal',    4.5),
]

REPORT_ONLY = [
    ('border on background',      'border',    'bg',     1.0),
]


def hex_to_rgb(h):
    h = h.strip().lstrip('#')
    if len(h) == 3:
        h = ''.join(c * 2 for c in h)
    if len(h) != 6:
        raise ValueError('bad hex %r' % h)
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def luminance(rgb):
    def chan(c):
        c = c / 255.0
        return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4
    r, g, b = (chan(x) for x in rgb)
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def ratio(fg, bg):
    a, b = luminance(hex_to_rgb(fg)), luminance(hex_to_rgb(bg))
    hi, lo = max(a, b), min(a, b)
    return (hi + 0.05) / (lo + 0.05)


def mix(c1, c2, t):
    """Linear RGB blend; t=0 is c1."""
    a, b = hex_to_rgb(c1), hex_to_rgb(c2)
    return '#%02x%02x%02x' % tuple(round(a[i] + (b[i] - a[i]) * t) for i in range(3))


def darken(c, t):
    return mix(c, '#000000', t)


def lighten(c, t):
    return mix(c, '#ffffff', t)


def adjust_until(fg, bg, minimum, darker_ok=True):
    """Nudge fg away from bg until it clears `minimum`."""
    best = fg
    for i in range(1, 41):
        t = i / 40.0
        cand = darken(fg, t) if darker_ok else lighten(fg, t)
        if ratio(cand, bg) >= minimum:
            return cand
        best = cand
    return best


def report(palettes, threshold=4.5):
    failures = []
    for name, cols in palettes.items():
        # A palette that declares on-accent is a dark one: its accent is light, so
        # the white-on-accent pairs do not apply to it and vice versa.
        is_dark = 'on-accent' in cols
        pairs = (PAIRS_DARK + REPORT_ONLY if is_dark
                 else PAIRS + REPORT_ONLY)
        print('\n=== %s ===%s' % (name, '  (dark)' if is_dark else ''))
        print('  %-28s %-8s %-8s %7s  %s' % ('pair', 'fg', 'bg', 'ratio', 'verdict'))
        for label, fg_k, bg_k, minimum in pairs:
            if fg_k not in cols or bg_k not in cols:
                continue
            r = ratio(cols[fg_k], cols[bg_k])
            ok = r >= minimum
            gated = minimum > 1.0        # REPORT_ONLY rows are informational
            if not ok and gated:
                failures.append((name, label, cols[fg_k], cols[bg_k], r, minimum))
            verdict = 'PASS' if ok else ('FAIL' if gated else 'note')
            print('  %-28s %-8s %-8s %6.2f:1  %s (min %.1f)'
                  % (label, cols[fg_k], cols[bg_k], r, verdict, minimum))
    return failures


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--palettes', help='JSON file: {name: {token: hex}}')
    ap.add_argument('--derive-night', action='store_true',
                    help='derive a Night palette that clears AA, print it as JSON')
    args = ap.parse_args()

    if args.derive_night:
        # Night is Navy's ink on a dark surface, not a third unrelated scheme.
        #
        # The first derivation got this wrong: it picked a LIGHT accent that reads
        # well as text on the dark background, then checked "white on accent" --
        # which is how the light palettes work, where the accent is dark and
        # carries white text. On a dark UI an accent button carries DARK text, and
        # white-on-light-accent measured 2.63:1. Both readings are needed, so both
        # are derived: the accent must read as text against the dark background
        # AND carry the button's own text colour at AA.
        bg = '#12151c'
        surface = '#1b1f28'
        accent = adjust_until('#7ba1ff', bg, 4.5)
        gold = adjust_until('#c9a97a', bg, 4.5)
        cols = {
            'red':   accent,
            'teal':  gold,
            'ink':   '#f2f4f8',
            'text':  adjust_until('#c3c9d6', bg, 4.5),
            'muted': adjust_until('#98a1b3', bg, 4.5),
            'border': adjust_until('#333a47', bg, 1.3),
            # Button text on the accent and on the gold: near-black, because both
            # accents are light in this palette.
            'on-accent': adjust_until('#0d1017', accent, 4.5, darker_ok=True),
            'on-gold':   adjust_until('#0d1017', gold, 4.5, darker_ok=True),
            'white': '#ffffff',
            'badge-red': adjust_until('#ff6b6b', surface, 4.5),
            'bg':    bg,
        }
        print(json.dumps(cols, indent=1))
        return 0

    palettes = {}
    if args.palettes:
        palettes = json.load(open(args.palettes))
    else:
        palettes = json.load(open(os.path.join(BASE, 'docker', 'palettes.json')))

    failures = report(palettes)
    if failures:
        print('\n%d below threshold:' % len(failures))
        for f in failures:
            print('  %-10s %-28s %s on %s = %.2f:1 (min %.1f)'
                  % (f[0], f[1], f[2], f[3], f[4], f[5]))
        return 1
    print('\nall pairs clear their thresholds')
    return 0


if __name__ == '__main__':
    sys.exit(main())