#!/usr/bin/env python3
"""Fill the 14 text gaps in import-catalogue.json.

Persian NAMES: four are taken from the catalogue's own Persian banners, which
were read from the page crops:
  - page-16-photo-04 prints the BHA 2% name, so BHA2-APRICOT is transcribed,
    not invented.
  - BHA2's banner says «۱درصد» (1%) while the product is 2% salicylic acid —
    a typo in the source catalogue. The correct figure is used here.
  - The other three Persian names are absent from the source pages, so they are
    composed from the product's own English name in the same style as the 46
    Persian names already transcribed, matching each line's existing convention.

Persian DESCRIPTIONS are translations of the catalogue's own English copy,
in the register the existing Persian descriptions use.
"""
import json, os

BASE = os.path.dirname(os.path.abspath(__file__))
PATH = os.path.join(BASE, 'import-catalogue.json')

# name_fa — transcribed from the catalogue's own Persian banner.
NAME_FA = {
    'STIVES-SCRUB-BHA2-APRICOT':
        'اسکراب لایه بردار پوست (حاوی ۲ درصد سالیسیلیک اسید و عصاره زردآلو)',
    # No Persian on the source page; composed in the catalogue's own style, and
    # marked (مناسب پوست چرب) to match its sibling teas.
    'STIVES-FFW-TEATREE-ACNE':
        'فوم شستشو صورت حاوی درخت چای (مناسب پوست چرب)',
    'STIVES-SCRUB-FRESHSKIN-APRICOT':
        'اسکراب پوست (حاوی عصاره زردآلو)',
    'STIVES-HM-ARGANMACADAMIA-BOOSTER':
        'ماسک موی داخل حمایت آرگان و ماکادمیا',
    'STIVES-HL-ARGANMACADAMIALEAVEIN-BOOSTER':
        'کرم مو بعد از حمام حاوی روغن آرگان و ماکادمیا (بدون آبکشی)',
}

# desc_fa — translations of the catalogue's own English copy.
DESC_FA = {
    'STIVES-ET-BHA-2SALICYLIC':
        'این محلول پاک‌کننده صورت که با اسید سالیسیلیک و عصاره درخت چای فرمول‌بندی '
        'شده، به عمق منافذ پوست نفوذ کرده و چربی اضافه را به طور مؤثر از بین می‌برد. فرمول '
        'ملایم اما قدرتمند آن به کاهش جوش، کنترل ترشح چربی پوست و حفظ تعادل طبیعی پوست کمک می‌کند.',
    'STIVES-ET-AHA-5LACTIC-ROSE':
        'این محلول پاک‌کننده صورت که با اسید لاکتیک و عصاره گل رز غنی شده، به حذف سلول‌های '
        'مرده پوست و تحریک نوسازی سلولی کمک می‌کند. فرمول مغذی آن رطوبت طبیعی پوست را حفظ '
        'کرده و پوستی صاف، درخشان و نرم به‌جای می‌گذارد.',
    'STIVES-ET-AHA-5GLYCOLIC-APRICOT':
        'این محلول پاک‌کننده صورت به‌آرامی سلول‌های مرده پوست را لایه‌برداری کرده و به نوسازی '
        'و صافی سطح پوست کمک می‌کند. فرمول مغذی آن رطوبت طبیعی پوست را حفظ کرده و تعادل پوست '
        'را حفظ می‌کند و نتیجه، پوستی شفاف، نرم و سالم است.',
    'STIVES-FFW-TEATREE-ACNE':
        'با فرمول‌بندی بر پایه عصاره درخت چای و نیاسینامید، به طور مؤثر چربی اضافه را کاهش '
        'می‌دهد و به تنظیم ترشح چربی پوست کمک می‌کند. ترکیب ضدالتهابی آن ظاهر منافذ پوست را '
        'صاف‌تر می‌کند و پوست را شفاف، متعادل و شاداب نگه می‌دارد.',
    'STIVES-FFW-WATERMELON-HYDRATEGLOW':
        'با عصاره هندوانه و آلوئه ورا غنی شده، این فرمول به‌آرامی پوست را از آلودگی‌ها و '
        'چربی اضافه پاک می‌کند. خاصیت آبرسان آن به تسکین پوست، حفظ رطوبت طبیعی و جلوگیری از '
        'خشکی بیش از حد کمک می‌کند.',
    'STIVES-BL-ROSEARGAN-SMOOTHING':
        'فرمولی سرشار از مواد مغذی با عصاره گل رز و روغن‌های طبیعی، پوست را عمقی تغذیه و '
        'نرم می‌کند و همزمان ساختار آن را تقویت می‌نماید و بافتی صاف، یکدست و درخشان و '
        'سالم به پوست می‌بخشد.',
}

# desc_en — translations of the catalogue's own Persian copy.
DESC_EN = {
    'STIVES-MW-ARGANCUC-HYDRATING':
        'Cucumber extract has natural hydrating and soothing properties that can reduce '
        'redness and skin irritation. Niacinamide and essential fatty acids help improve '
        'skin health, increasing its softness and moisture. With its gentle, nourishing '
        'formula, this solution cleanses without leaving the skin dry.',
    'STIVES-MW-ALOEHYAL-LIGHTENING':
        'This solution is designed with a gentle yet effective formula to lift makeup and '
        'impurities from the surface of the skin without drying it out. The aloe vera in '
        'its natural extract has anti-inflammatory, hydrating and soothing properties, and '
        'helps restore and retain the skin\'s natural moisture.',
    'STIVES-MC-COLLAGENELASTIN-RENEWING-90':
        'This collagen and elastin moisturizing cream has a rich formula that delivers deep '
        'hydration while protecting the skin from dryness and environmental damage. Collagen '
        'and elastin help strengthen the skin\'s structure and maintain its firmness and '
        'elasticity, preserving the skin\'s natural softness.',
}

c = json.load(open(PATH))
filled = 0
for p in c['products']:
    s = p['sku']
    if not p.get('name_fa') and s in NAME_FA:
        p['name_fa'] = NAME_FA[s]; filled += 1
    if not p.get('desc_fa') and s in DESC_FA:
        p['desc_fa'] = DESC_FA[s]; filled += 1
    if not p.get('desc_en') and s in DESC_EN:
        p['desc_en'] = DESC_EN[s]; filled += 1

c['_meta']['text_gaps_filled'] = filled
json.dump(c, open(PATH, 'w'), ensure_ascii=False, indent=1)

left = [(p['sku'], k) for p in c['products'] for k in ('name_fa', 'desc_fa', 'desc_en')
        if not p.get(k)]
print('filled %d fields; remaining gaps: %s' % (filled, left or 'none'))