#!/usr/bin/env python3
"""Compose the care landing pages' hero and guide images from St. Ives packshots.

Read-only against wp-content/uploads. Writes only into
themes/lylyrose/assets/images/{skincare,haircare}/.

The packshots are portrait (~355x900) on a white background. A wide hero is
built by taking a row of them, scaling to a common height, laying them out with
overlap, then blurring that layer so it reads as a soft bed rather than a row of
bottles. A single sharp packshot is placed over it. No text is baked in: all
copy is HTML.

Idempotent: re-running overwrites the same files with the same bytes.

Usage: python3 compose-care-images.py [--dry-run]
"""

from __future__ import annotations

import argparse
import glob
import os
import sys

try:
    from PIL import Image, ImageFilter, ImageDraw
except ImportError:
    sys.exit("Pillow is required: pip install Pillow")

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
UPLOADS = os.path.join(REPO, "wordpress", "wp-content", "uploads", "2026", "10")
THEME_IMG = os.path.join(REPO, "wordpress", "wp-content", "themes", "lylyrose", "assets", "images")
# The Digikala mirror holds the only real lifestyle photography in this repo: the
# packshots there come as AI-rendered scenes (window light, wood, flowers, water)
# in addition to flat product shots. Tile grounds are cropped from those.
DK = os.path.join(REPO, "new_products", "digikala") + os.sep

# The scene sources that contain no baked-in marketing copy.
COC02 = DK + "STIVES-BL-COCONUTORCHID-SOFTENING/02.jpg"
COL03 = DK + "STIVES-BL-COLLAGENELASTIN-RENEWING/03.jpg"
ROS02 = DK + "STIVES-BL-ROSEARGAN-SMOOTHING/02.jpg"
SEA02 = DK + "STIVES-BW-SEASALTKELP-EXFOLIATING/02.jpg"

# Skin tone for the composition ground. Matches the care palette's soft surface;
# the panels behind the images in CSS are the same family, so the seam is hidden.
GROUND = (247, 243, 240)

HERO_W, HERO_H = 1680, 403
GUIDE_W, GUIDE_H = 600, 480

# Tiles match the perfume family tiles exactly, so the two page types line up.
TILE_W, TILE_H = 320, 154

# slug -> list of packshot SKUs to lay out, and which one stays sharp.
# `tiles` maps a subcategory slug to the images that tile is composed from —
# normally every product in that subcategory, so the tile reads as that shelf.
PAGES = {
    "skincare": {
        "bed": [
            "STIVES-BL-COLLAGENELASTIN-RENEWING",
            "STIVES-MC-COLLAGENELASTIN-RENEWING-90",
            "STIVES-FFW-CHAMOMILE-CALMSOOTHE",
            "STIVES-ET-BHA-2SALICYLIC",
            "STIVES-SPF50-COLLAGENELASTIN",
            "STIVES-BW-COCONUTORCHID-HYDRATING",
        ],
        "hero": "STIVES-MC-COLLAGENELASTIN-RENEWING-90",
        "guide": "STIVES-SPF50-COLLAGENELASTIN",
        "tiles": {
            # (source scene, crop box as fractions). Each box frames the scene's
            # props and stays clear of the pack and of any copy baked into the
            # source. Sources: COC02 = coconut+orchid scene, COL03 = plant/fern
            # scene, ROS02 = rose+nuts scene, SEA02 = sea-salt rock scene.
            "facial-cleanser": (COC02, (0.06, 0.60, 0.30, 0.96)),   # orchid close-up
            "face-foam": (COC02, (0.0, 0.50, 0.30, 1.0)),           # coconut on wood
            "exfoliant-toner": (ROS02, (0.0, 0.58, 0.26, 0.84)),    # rose + nuts
            "moisturizer": (COL03, (0.70, 0.18, 0.98, 0.54)),       # fern
            "sunscreen": (SEA02, (0.14, 0.70, 0.52, 1.0)),          # water + rock
            "body-scrub": (SEA02, (0.0, 0.66, 0.30, 1.0)),          # water wash
            "body-care": (ROS02, (0.0, 0.30, 0.20, 0.72)),          # pink roses in a vase
        },
    },
    "haircare": {
        "bed": [
            "STIVES-HM-ARGANMACADAMIASHAMPOO-BOOSTER",
            "STIVES-HM-COLLAGENKERATINSHAMPOO-REPAIRING",
            "STIVES-HS-ARGANMACADAMIACOND-BOOSTER",
            "STIVES-HL-BOTOXLEAVEIN-NOURISHING",
            "STIVES-HO-ARGANOIL-100",
            "STIVES-HM-BOTOXMASK-NOURISHING",
        ],
        "hero": "STIVES-HM-ARGANMACADAMIASHAMPOO-BOOSTER",
        "guide": "STIVES-HO-ARGANOIL-100",
        "tiles": {
            "hair-treatment": (COL03, (0.70, 0.18, 0.98, 0.54)),    # fern
            "hair-styling": (SEA02, (0.14, 0.70, 0.52, 1.0)),       # water + rock
            "hair-oil": (ROS02, (0.0, 0.58, 0.26, 0.84)),           # rose + nuts
        },
    },
}


def find_packshot(name: str) -> str | None:
    """Locate a packshot.

    `name` is either a bare SKU (the hero/bed entries) or a full filename as the
    catalogue stores it (the tile entries, whose file may be a hashed upload
    name rather than the SKU). Exact filename is tried first.
    """
    if name.lower().endswith((".webp", ".jpg", ".jpeg", ".png")):
        exact = os.path.join(UPLOADS, name)
        if os.path.exists(exact):
            return exact
        stem = os.path.splitext(name)[0]
    else:
        stem = name
    for ext in (".webp", ".jpg", ".png"):
        direct = os.path.join(UPLOADS, stem + ext)
        if os.path.exists(direct):
            return direct
    # Fall back to any non-resized sibling.
    for path in sorted(glob.glob(os.path.join(UPLOADS, stem + "*"))):
        base = os.path.basename(path)
        if any(f"-{n}x{n}." in base for n in (100, 150, 300)) or base.endswith(".avif"):
            continue
        if base.lower().endswith((".webp", ".jpg", ".jpeg", ".png")):
            return path
    return None


def load_rgba(path: str) -> Image.Image:
    """Load a packshot and knock out its white background.

    The packshots are JPEG/WebP on white, not transparent PNGs, so pasting one
    straight onto the composed bed leaves a white rectangle. Alpha is taken from
    the distance to white (alpha = 255 - min(r,g,b)) and the colour is
    un-premultiplied, the standard white->alpha key. The composition ground is
    near-white, so any soft edge reads cleanly.
    """
    img = Image.open(path).convert("RGB")
    px = img.load()
    out = Image.new("RGBA", img.size)
    op = out.load()
    for y in range(img.height):
        for x in range(img.width):
            r, g, b = px[x, y]
            a = 255 - min(r, g, b)
            if a == 0:
                op[x, y] = (r, g, b, 0)
            elif a == 255:
                op[x, y] = (r, g, b, 255)
            else:
                # Un-premultiply against white so anti-aliased edges keep colour.
                f = 255 / a
                nr = max(0, min(255, round((r - 255) * f + 255)))
                ng = max(0, min(255, round((g - 255) * f + 255)))
                nb = max(0, min(255, round((b - 255) * f + 255)))
                op[x, y] = (nr, ng, nb, a)
    # Fully transparent pixels carry the original colour; clear them so blurring
    # the bed does not pull a halo out of the discarded white.
    out.paste((0, 0, 0, 0), mask=out.getchannel("A").point(lambda p: 255 if p == 0 else 0))
    return out


def trim_white(img: Image.Image, tol: int = 12) -> Image.Image:
    """Crop the transparent margin around a keyed packshot."""
    bbox = img.getchannel("A").point(lambda p: 255 if p > tol else 0).getbbox()
    return img.crop(bbox) if bbox else img


def fit_height(img: Image.Image, h: int) -> Image.Image:
    w = max(1, round(img.width * h / img.height))
    return img.resize((w, h), Image.LANCZOS)


def paste_center(canvas: Image.Image, img: Image.Image, cx: int, cy: int) -> None:
    canvas.alpha_composite(img, (cx - img.width // 2, cy - img.height // 2))


def build_bed(skus: list[str], w: int, h: int, blur: int = 14) -> Image.Image:
    """A blurred row of packshots, widened to fill `w`."""
    layers = []
    for sku in skus:
        path = find_packshot(sku)
        if not path:
            print(f"    WARN no packshot for {sku}", file=sys.stderr)
            continue
        layers.append(fit_height(trim_white(load_rgba(path)), int(h * 1.15)))
    if not layers:
        return Image.new("RGBA", (w, h), GROUND + (255,))

    total = sum(l.width for l in layers)
    gap = max(0, (w - total)) // max(1, len(layers) - 1) if len(layers) > 1 else 0
    # If the row is narrower than the canvas, spread the gaps; if wider, overlap.
    row_w = total + gap * (len(layers) - 1)
    if row_w < w and len(layers) > 1:
        gap = (w - total) // (len(layers) - 1)
    elif row_w > w:
        gap = -((total - w) // max(1, len(layers) - 1))
    row_w = total + gap * (len(layers) - 1)

    row = Image.new("RGBA", (max(row_w, 1), int(h * 1.15)), (0, 0, 0, 0))
    x = 0
    for l in layers:
        row.alpha_composite(l, (x, (row.height - l.height) // 2))
        x += l.width + gap

    # Centre the row on the canvas.
    canvas = Image.new("RGBA", (w, h), GROUND + (255,))
    canvas.alpha_composite(row, ((w - row.width) // 2, (h - row.height) // 2))
    return canvas.filter(ImageFilter.GaussianBlur(blur))


def build_hero(spec: dict) -> Image.Image:
    canvas = build_bed(spec["bed"], HERO_W, HERO_H, blur=16)
    path = find_packshot(spec["hero"])
    if path:
        hero = fit_height(trim_white(load_rgba(path)), int(HERO_H * 0.92))
        paste_center(canvas, hero, int(HERO_W * 0.72), HERO_H // 2)
    return canvas


def build_guide(spec: dict) -> Image.Image:
    canvas = build_bed(spec["bed"], GUIDE_W, GUIDE_H, blur=18)
    path = find_packshot(spec["guide"])
    if path:
        guide = fit_height(trim_white(load_rgba(path)), int(GUIDE_H * 0.86))
        paste_center(canvas, guide, int(GUIDE_W * 0.66), GUIDE_H // 2)
    return canvas


def scene_crop(path: str, box: tuple[float, float, float, float]) -> Image.Image:
    """Crop a fraction-box out of a photograph and fit it to the tile box.

    `box` is (x0, y0, x1, y1) as fractions of the source. Each tile names its own
    box because the scenes differ: the clean background sits bottom-left in one,
    right-of-bottle in another, and the product plus any baked-in copy must be
    kept out of frame.
    """
    rgb = Image.open(path).convert("RGB")
    w, h = rgb.size
    x0, y0, x1, y1 = (int(box[0] * w), int(box[1] * h), int(box[2] * w), int(box[3] * h))
    crop = rgb.crop((x0, y0, x1, y1))
    # Cover-fit: scale so both dimensions fill, then centre-crop the overflow.
    scale = max(TILE_W / crop.width, TILE_H / crop.height)
    fitted = crop.resize((max(1, round(crop.width * scale)), max(1, round(crop.height * scale))), Image.LANCZOS)
    left = (fitted.width - TILE_W) // 2
    top = (fitted.height - TILE_H) // 2
    tile = fitted.crop((left, top, left + TILE_W, top + TILE_H))
    # A hint of softening so a photographic ground does not compete with the
    # tile's own label, without losing the sense that it is a photo.
    return tile.filter(ImageFilter.GaussianBlur(0.8)).convert("RGBA")


def build_tile(spec) -> Image.Image:
    """A tile background from a real lifestyle photo.

    `spec` is (source path, crop box) from the PAGES tile table. Falls back to a
    soft ground so a tile never renders blank.
    """
    if not spec:
        return Image.new("RGBA", (TILE_W, TILE_H), GROUND + (255,))
    path, box = spec
    if not os.path.exists(path):
        print(f"    WARN missing tile source {path}", file=sys.stderr)
        return Image.new("RGBA", (TILE_W, TILE_H), GROUND + (255,))
    return scene_crop(path, box)


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run", action="store_true")
    # wp-content is www-data-owned and this script runs as the session user, so
    # the default target is usually not writable. Stage elsewhere and copy in
    # through the container: docker exec -u root cp -r …
    ap.add_argument("--outdir", default=THEME_IMG, help="root to write <slug>/ into")
    args = ap.parse_args()

    if not os.path.isdir(UPLOADS):
        sys.exit(f"uploads dir not found: {UPLOADS}")

    written = 0
    for slug, spec in PAGES.items():
        outdir = os.path.join(args.outdir, slug)
        print(f"=== {slug} -> {os.path.relpath(outdir, REPO)}")
        if not args.dry_run:
            os.makedirs(outdir, exist_ok=True)

        jobs = [("hero.webp", build_hero), ("guide.webp", build_guide)]
        # One tile per subcategory, named tile-<slug>.webp so the page config and
        # the asset stay in the same vocabulary as the taxonomies.
        for sub, names in spec.get("tiles", {}).items():
            jobs.append((f"tile-{sub}.webp", lambda s, n=names: build_tile(n)))

        for name, builder in jobs:
            img = builder(spec)
            flat = Image.new("RGB", img.size, GROUND)
            flat.paste(img, (0, 0), img)
            out = os.path.join(outdir, name)
            if args.dry_run:
                print(f"  would write {name} {img.size}")
            else:
                flat.save(out, "WEBP", quality=82, method=6)
                print(f"  wrote {name} {img.size} {os.path.getsize(out)}B")
                written += 1

    print(f"{'DRY RUN' if args.dry_run else 'DONE'}: {written} files written")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
