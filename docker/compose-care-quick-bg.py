#!/usr/bin/env python3
"""Compose full-bleed background photos for the care landing pages' 3 quick cards.

Read-only against new_products/digikala. Writes only into
themes/lylyrose/assets/images/{skincare,haircare}/quick-bg-*.webp.

Why crops rather than the existing quick-*.webp: those are framed on the pack and
carry the pack's baked-in marketing copy, which would sit under the card's own
headline. Each background here is a region of the same staged lifestyle scenes
that holds no pack and no copy — the page's hero is built the same way (a blurred
bed of packshots), so the treatment matches the section above it.

A mild blur (BG_BLUR) removes any product edge or copy that creeps into a crop,
so a card is legible whatever lands behind it.

Idempotent: re-running overwrites the same files with the same bytes.

Usage:
  python3 compose-care-quick-bg.py             # write into the theme
  python3 compose-care-quick-bg.py --preview   # contact sheet to /tmp, no theme write
"""

from __future__ import annotations

import argparse
import os
import sys

try:
    from PIL import Image, ImageDraw, ImageFilter
except ImportError:
    sys.exit("Pillow is required: pip install Pillow")

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
THEME_IMG = os.path.join(
    REPO, "wordpress", "wp-content", "themes", "lylyrose", "assets", "images"
)
DK = os.path.join(REPO, "new_products", "digikala") + os.sep

COC02 = DK + "STIVES-BL-COCONUTORCHID-SOFTENING/02.jpg"
COL03 = DK + "STIVES-BL-COLLAGENELASTIN-RENEWING/03.jpg"
ROS02 = DK + "STIVES-BL-ROSEARGAN-SMOOTHING/02.jpg"
SEA02 = DK + "STIVES-BW-SEASALTKELP-EXFOLIATING/02.jpg"
ARG02 = DK + "STIVES-HM-ARGANMACADAMIA-BOOSTER/02.jpg"
COLM = DK + "STIVES-HM-COLLAGENKERATINMASK-REPAIRING/02.jpg"
BOT02 = DK + "STIVES-HM-BOTOXMASK-NOURISHING/02.jpg"

# 2x the widest a card renders (~610px on the quiz card at 1440).
BG_W, BG_H = 1200, 640
# Softens a product edge or a stray glyph without losing the scene.
BG_BLUR = 4.0

# slug -> {output name: (source scene, crop box as fractions)}
#
# Every box was chosen against a ruled contact sheet to exclude the pack and the
# baked-in Persian copy: the quiet props are the fern (COL03 right), the marble
# and the rose vase (ROS02), the surf (SEA02 right/bottom), and the pastel
# pedestal + sphere that sits to the right of the pack in every HM 02 scene.
PAGES = {
    "skincare": {
        "quick-bg-quiz.webp": (COC02, (0.00, 0.55, 0.30, 1.00)),  # palm fronds + orchid
        "quick-bg-a.webp":    (COL03, (0.60, 0.30, 1.00, 0.85)),  # green plant, no pack
        "quick-bg-b.webp":    (SEA02, (0.00, 0.65, 0.45, 1.00)),  # rock + water + leaf
    },
    "haircare": {
        "quick-bg-quiz.webp": (ARG02, (0.50, 0.50, 1.00, 1.00)),  # blush pedestal + sphere
        "quick-bg-a.webp":    (COLM,  (0.50, 0.50, 1.00, 1.00)),  # mint pedestal + sphere
        "quick-bg-b.webp":    (BOT02, (0.50, 0.50, 1.00, 1.00)),  # lilac pedestal + sphere
    },
}


def crop_fill(path: str, box, w: int = BG_W, h: int = BG_H) -> Image.Image:
    """Crop a fraction-box and cover-fit it to w x h, then soften."""
    rgb = Image.open(path).convert("RGB")
    sw, sh = rgb.size
    x0, y0, x1, y1 = (int(box[0] * sw), int(box[1] * sh), int(box[2] * sw), int(box[3] * sh))
    c = rgb.crop((x0, y0, x1, y1))
    s = max(w / c.width, h / c.height)
    f = c.resize((max(1, round(c.width * s)), max(1, round(c.height * s))), Image.LANCZOS)
    l, t = (f.width - w) // 2, (f.height - h) // 2
    out = f.crop((l, t, l + w, t + h))
    if BG_BLUR:
        out = out.filter(ImageFilter.GaussianBlur(BG_BLUR))
    return out


def preview() -> None:
    os.makedirs("/tmp/carebg", exist_ok=True)
    cols, rows = 3, len(PAGES)
    tw, th = 360, 192
    sheet = Image.new("RGB", (cols * tw, rows * th), "white")
    for r, (slug, spec) in enumerate(PAGES.items()):
        for c, (name, (path, box)) in enumerate(spec.items()):
            im = crop_fill(path, box)
            im.save(f"/tmp/carebg/{slug}-{name}.png")
            t = im.copy()
            t.thumbnail((tw, th))
            d = ImageDraw.Draw(t)
            d.rectangle([0, 0, 200, 13], fill=(0, 0, 0))
            d.text((3, 3), f"{slug} {name}", fill=(255, 255, 255))
            sheet.paste(t, (c * tw, r * th))
    sheet.save("/tmp/carebg/sheet.png")
    print("wrote /tmp/carebg/sheet.png")


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--preview", action="store_true")
    ap.add_argument("--outdir", default=THEME_IMG)
    args = ap.parse_args()

    if args.preview:
        preview()
        return 0

    written = 0
    for slug, spec in PAGES.items():
        outdir = os.path.join(args.outdir, slug)
        os.makedirs(outdir, exist_ok=True)
        for name, (path, box) in spec.items():
            if not os.path.exists(path):
                print(f"  WARN missing source {path}", file=sys.stderr)
                continue
            out = os.path.join(outdir, name)
            crop_fill(path, box).save(out, "WEBP", quality=80, method=6)
            print(f"  wrote {slug}/{name} {os.path.getsize(out)}B")
            written += 1
    print(f"DONE: {written} files written")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
