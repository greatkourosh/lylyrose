#!/usr/bin/env python3
"""Build the import catalogue: correct packshots, filled names, category, price.

Packshot recovery. The catalogue's own `packshot` field is wrong on many SKUs.
Four automatic classifiers were tried and all failed for one shared reason — they
guessed a rule for "this is a bottle photo" instead of deriving the layout. Aspect
ratio misfiled squat jars (~1.15); OCR similarity always preferred the name block,
which is a cleaner render of the product's own words than the photograph is; word
count picked the description blocks; and central pixel mass ranked the decorative
strips highest.

The layout is regular and measurable, so it is used directly. 19 of 25 pages
carry exactly three crops per product after two full-bleed decorative strips:

    [ N name blocks ][ N PACKSHOTS ][ N description blocks ]

so packshot[i] is the crop at index N+i. Checked against pages already confirmed
by eye: page 5 gives 05/06 and page 9 gives 06/07/08, both matching. The model
also independently reproduces the page-18 shift verified by reading the tubes.

The other 6 pages carry a fourth crop (a Persian banner) or otherwise vary, so
they are mapped from OCR of the printed label, which names the product outright.
Page 23 is deliberately left unmapped: both of its bottles OCR to zero words, so
there is no evidence for either and one product ships without a photo.

Pricing is per product type, as chosen, so it does not depend on a volume that
only appears on the labels.
"""
import json, os, glob, struct, re
from collections import defaultdict

BASE = os.path.dirname(os.path.abspath(__file__))

# --- the six irregular pages, mapped from OCR of the printed label -----------
# Each entry: sku -> crop. Verified by the label text, quoted in the comment.
OCR_MAPPED = {
    # 03/04 name blocks, 05/06 jars ("Sr lves CALM & SOOTHING FOAMING FACE WASH",
    # "St Ives GLOW & MOISTURE FOAMING FACE WASH"), 07/08 descriptions, 09 Persian.
    'STIVES-FFW-CHAMOMILE-CALMSOOTHE': 'page-11-photo-05.jpg',
    'STIVES-FFW-APRICOT-GLOWMOIST': 'page-11-photo-06.jpg',
    # 03/04 names, 05/06 bottles ("sea salt & pacific kelp exfoliating body wash",
    # "fresh peach & jasmine exfoliating body wash"), 07/08 descriptions.
    'STIVES-BW-SEASALTKELP-EXFOLIATING': 'page-12-photo-05.jpg',
    'STIVES-BW-PEACHJASMINE-EXFOLIATING': 'page-12-photo-06.jpg',
    # 03/04 names, 05/06 jars ("exfoliant green tea & bamboo ... 1% salicylic
    # acid", "BHA exfoliant apricot ... 2% salicylic acid"), 07/08 descriptions.
    'STIVES-SCRUB-BHA1-GREENTEA-BAMBOO': 'page-16-photo-05.jpg',
    'STIVES-SCRUB-BHA2-APRICOT': 'page-16-photo-06.jpg',
    # 03 name, 04/05 jars ("FRESH SKIN ...", "ACNE CONTROL ... TEA TREE &
    # APRICOT EXTRACT"), 06/07 descriptions.
    'STIVES-SCRUB-TEATREEAPRICOT-ACNE': 'page-17-photo-05.jpg',
    'STIVES-SCRUB-FRESHSKIN-APRICOT': 'page-17-photo-04.jpg',
    # 03/04 names, 05/06 bottles ("REPAIRING CONDITIONING SPRAY ... 10 BENEFITS
    # IN 1", "REPAIRING LEAVE-IN ..."), 07/08 descriptions.
    'STIVES-HS-COLLAGENKERATINCOND-REPAIRING': 'page-28-photo-05.jpg',
    'STIVES-HL-COLLAGENKERATINLEAVEIN-REPAIRING': 'page-28-photo-06.jpg',
}

# Page 23's single product has two portrait bottles and both OCR to zero words,
# so nothing distinguishes them. It ships without a photo rather than guessing.
NO_PHOTO = {'STIVES-HM-CURLREVIVE-SOFTSHINY'}

# --- categories and prices, per product type -------------------------------
CATS = {
    'FC':    ('facial-cleanser', 'پاک‌کننده صورت', 385000),
    'MW':    ('facial-cleanser', 'پاک‌کننده صورت', 385000),
    'FFW':   ('face-foam',       'فوم شستشوی صورت', 365000),
    'ET':    ('exfoliant-toner', 'تونر لایه‌بردار صورت', 520000),
    'SCRUB': ('body-scrub',      'اسکراب پوست', 610000),
    'MC':    ('moisturizer',     'کرم مرطوب‌کننده', 690000),
    'SPF50': ('sunscreen',       'کرم ضدآفتاب', 980000),
    'BW':    ('body-care',       'شامپو و لوسیون بدن', 720000),
    'BL':    ('body-care',       'شامپو و لوسیون بدن', 720000),
    'HM':    ('hair-treatment',  'شامپو و ماسک مو', 780000),
    'HL':    ('hair-styling',    'محصولات حالت‌دهنده مو', 890000),
    'HS':    ('hair-styling',    'محصولات حالت‌دهنده مو', 890000),
    'HP':    ('hair-styling',    'محصولات حالت‌دهنده مو', 890000),
    'HO':    ('hair-oil',        'روغن مو', 1250000),
}

PARENTS = {
    'skin-care': 'مراقبت از پوست',
    'hair-care': 'مراقبت از مو',
}
CAT_PARENT = {
    'facial-cleanser': 'skin-care', 'face-foam': 'skin-care',
    'exfoliant-toner': 'skin-care', 'body-scrub': 'skin-care',
    'moisturizer': 'skin-care', 'sunscreen': 'skin-care', 'body-care': 'skin-care',
    'hair-treatment': 'hair-care', 'hair-styling': 'hair-care', 'hair-oil': 'hair-care',
}


def jpeg_size(path):
    d = open(path, 'rb').read()
    i = 2
    while i < len(d) - 9:
        if d[i] != 0xFF:
            i += 1
            continue
        m = d[i + 1]
        if m in (0xC0, 0xC1, 0xC2, 0xC3):
            h, w = struct.unpack('>HH', d[i + 5:i + 9])
            return w, h
        if m in (0xD8, 0xD9) or 0xD0 <= m <= 0xD7:
            i += 2
            continue
        i += 2 + struct.unpack('>H', d[i + 2:i + 4])[0]
    return 0, 0


def main():
    cat = json.load(open(os.path.join(BASE, 'stives-products.json')))
    dims = {os.path.basename(f): jpeg_size(f)
            for f in glob.glob(os.path.join(BASE, 'photos', '*.jpg'))}

    by_page = defaultdict(list)
    for p in cat['products']:
        by_page[p['page']].append(p)

    out = {'_meta': {
        'source': 'stives-products.json + verified packshot remap',
        'price_basis': 'per product type (owner decision 2026-10-04)',
        'photos': 'only where the packshot could be verified; see photo_note',
    }, 'parents': PARENTS, 'categories': {}, 'products': []}

    changed, nophoto = [], []
    for page in sorted(by_page):
        prods = by_page[page]
        sibs = sorted(n for n in dims if n.startswith('page-%02d-' % page))
        k = 0
        while k < len(sibs) and dims[sibs[k]][1] >= 1900:
            k += 1
        body = sibs[k:]
        n = len(prods)
        regular = (len(body) == 3 * n)

        for i, p in enumerate(prods):
            if p['sku'] in NO_PHOTO:
                shot = None
            elif p['sku'] in OCR_MAPPED:
                shot = OCR_MAPPED[p['sku']]
            elif regular:
                shot = body[n + i]
            else:
                shot = None
            if shot != p['packshot']:
                changed.append((p['sku'], p['packshot'], shot))
            if shot is None:
                nophoto.append(p['sku'])

            fam = p['sku'].split('-')[1]
            slug, label, price = CATS[fam]
            out['categories'].setdefault(slug, {'label': label,
                                                'parent': CAT_PARENT[slug]})
            out['products'].append({
                'sku': p['sku'], 'page': page,
                'name_fa': p['name_fa'], 'name_en': p['name_en'],
                'variant_en': p['variant_en'], 'variant_fa': p['variant_fa'],
                'desc_fa': p['desc_fa'], 'desc_en': p['desc_en'],
                'packshot': shot, 'original_packshot': p['packshot'],
                'cat': slug, 'price': price,
            })

    json.dump(out, open(os.path.join(BASE, 'import-catalogue.json'), 'w'),
              ensure_ascii=False, indent=1)

    print('packshot remapped for %d of %d SKUs' % (len(changed), len(out['products'])))
    for sku, a, b in changed:
        print('   %-38s %-22s -> %s' % (sku, a, b or 'NO PHOTO'))
    print('\nno photo: %d (%s)' % (len(nophoto), ', '.join(nophoto)))
    missing = [f for f in (os.path.join(BASE, 'photos', p['packshot'])
                           for p in out['products'] if p['packshot']) if not os.path.exists(f)]
    print('packshot files referenced but absent: %d' % len(missing))

    # Full text of everything still needing a translation or a Persian name.
    print('\n=== GAPS TO FILL ===')
    for p in out['products']:
        gaps = []
        if not p['name_fa']:
            gaps.append('name_fa')
        if not p['desc_fa']:
            gaps.append('desc_fa')
        if not p['desc_en']:
            gaps.append('desc_en')
        if gaps:
            print('\n--- %s  [%s]' % (p['sku'], ','.join(gaps)))
            if 'desc_fa' in gaps:
                print('EN: %s' % p['desc_en'])
            if 'desc_en' in gaps:
                print('FA: %s' % p['desc_fa'])


if __name__ == '__main__':
    main()