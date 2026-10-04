#!/usr/bin/env python3
"""Ten products share a title with another product in the catalogue.

Two different collisions are here, and neither is a duplicate-insert bug -- every
one of these is a distinct SKU, so the suite is right to flag them as
indistinguishable: a shopper cannot tell two sizes of one product apart by name,
and neither can a search.

  * Six moisturisers: the same cream sold in two sizes, named identically. The
    size is appended to the title, taken from the variant the catalogue names
    them by (90 / 170 / 621).
  * Four hair products: the transcription gave the collagen-keratin spray and
    leave-in the SAME Persian names as the curl primer and curl revitaliser.
    These are different products entirely, so they are given their own names
    rather than a size suffix.

Only the title changes; the SKU does not, so anything already referencing these
products keeps resolving to the right one.
"""
import json, os

BASE = os.path.dirname(os.path.abspath(__file__))
PATH = os.path.join(BASE, 'import-catalogue.json')

# sku -> the exact replacement title.
NEW_NAME = {
    # Same cream, two sizes.
    'STIVES-MC-COLLAGENELASTIN-RENEWING-90':
        'کرم مرطوب کننده پوست حاوی کلاژن و الاستین ۹۰ میل',
    'STIVES-MC-COLLAGENELASTIN-RENEWING-170':
        'کرم مرطوب کننده پوست حاوی کلاژن و الاستین ۱۷۰ میل',
    'STIVES-MC-ARGANGRAPE-NOURISHING-621':
        'کرم مرطوب کننده پوست حاوی روغن آرگان و هسته انگور ۶۲۱ گرم',
    'STIVES-MC-ARGANGRAPE-NOURISHING-170':
        'کرم مرطوب کننده پوست حاوی روغن آرگان و هسته انگور ۱۷۰ گرم',
    'STIVES-MC-ALOEVERA-LIGHTENING-621':
        'کرم مرطوب کننده پوست حاوی آلوئه ورا و ویتامین سی ۶۲۱ گرم',
    'STIVES-MC-ALOEVERA-LIGHTENING-170':
        'کرم مرطوب کننده پوست حاوی آلوئه ورا و ویتامین سی ۱۷۰ گرم',
    # Different products that were given each other's names.
    'STIVES-HS-COLLAGENKERATINCOND-REPAIRING':
        'اسپری نرم کننده موی کلاژن کراتین (ترمیم کننده)',
    'STIVES-HP-CURLCOMPLEX-BOUNCY':
        'پرایمر محافظت کننده مخصوص موهای فر',
    'STIVES-HL-COLLAGENKERATINLEAVEIN-REPAIRING':
        'کرم موی کلاژن کراتین بعد از حمام (بدون آبکشی)',
    'STIVES-HL-CURLREVITALIZE-ELASTIC':
        'کرم مو بعد از حمام مخصوص موهای فر (بدون آبکشی)',
}

c = json.load(open(PATH))
changed = 0
for p in c['products']:
    new = NEW_NAME.get(p['sku'])
    if new and new != p['name_fa']:
        print('%-38s -> %s' % (p['sku'], new))
        p['name_fa'] = new
        changed += 1

json.dump(c, open(PATH, 'w'), ensure_ascii=False, indent=1)

titles = [p['name_fa'] for p in c['products']]
dupes = {t for t in titles if titles.count(t) > 1}
print('\nrenamed %d; remaining duplicate titles: %s' % (changed, dupes or 'none'))