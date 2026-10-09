#!/usr/bin/env python3
"""Emit the Perfume Finder profile for each fragrance product, as JSON.

The catalogue was imported from aroma_store and resells a small set of real
perfumes under many generic house names (ژاورو, کلپک, نیفتی, فراگرنس پرشیا 116,
هارلینگن, اسکلاره, ایفوریا, لالیکو, لطافه, ژدفون, رودیر). Fourteen products are
Dior Sauvage alone. The house name is not a fragrance; the MODEL is, so profiles
are authored per real perfume and matched by model name.

Only fragrance products are listed. The finder queries every product with no
category filter and gates solely on the fragrance axes, so a body lotion given a
fragrance family would appear in perfume recommendations.

Axis vocabulary is not ours: ASC_Perfume_Finder::vocabularies() and
longevity_levels() define the only terms the quiz can ever answer with, and the
checker below refuses to emit anything outside them.

Output: docker/finder-profiles.json  (consumed by seed-finder-data.php)
"""
import json
import re
from pathlib import Path

# ---------------------------------------------------------------------------
# The controlled vocabulary. Copied from ASC_Perfume_Finder, and asserted against
# it in the PHP importer rather than trusted here.
# ---------------------------------------------------------------------------
FAMILIES   = ["گل", "میوه", "چوب", "ادویه", "آکواتیک", "شرقی"]
OCCASIONS  = ["روزمره", "مهمانی", "محل کار", "ورزشی", "سفر", "مراسم رسمی"]
SEASONS    = ["بهار", "تابستان", "پاییز", "زمستان"]
PERSONAL   = ["کلاسیک", "مدرن", "جسور", "مؤدب", "شیطون", "آرام"]
GENDERS    = ["مردانه", "زنانه", "یونیسکس"]
LONGEVITY  = ["کوتاه", "متوسط", "بلند", "خیلی بلند"]

# ---------------------------------------------------------------------------
# Profiles per real perfume. Ordered most-specific first: the matcher takes the
# first rule whose needle appears in the product title.
#
# notes is the pyramid, top/heart/base, as real note material in Persian.
# ---------------------------------------------------------------------------
PROFILES = [
    # -- longest, most specific model names first: the matcher takes the first
    #    rule that fires, so "اسپلندور بلو" must precede the bare "بلو شنل".
    ("اسپلندور بلک", dict(                  # Francex / Splendor Black
        family=["چوب", "ادویه"], occasion=["روزمره", "محل کار", "مهمانی"],
        season=["پاییز", "زمستان"], personality=["جسور", "مدرن"],
        longevity="بلند",
        notes={"top": "فلفل سیاه، برگاموت",
               "heart": "دارچین، گل",
               "base": "چوب تیره، چرم، تنباکو"})),
    ("بلو د شنل", dict(
        family=["آکواتیک", "گل", "ادویه"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "مؤدب", "مدرن"],
        longevity="بلند",
        notes={"top": "لیمو، برگاموت",
               "heart": "اسانس گلابی، ژربرگ",
               "base": "چوب صندل، مشک"})),
    ("بلوشنل", dict(                          # Chanel Bleu (no space)
        family=["آکواتیک", "گل", "ادویه"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "لیمو، برگاموت",
               "heart": "گلابی، ژربرگ",
               "base": "چوب صندل"})),

    # -- multi-match rules ------------------------------------------
    ("ساواج دیور الکسیر", dict(          # Dior Sauvage Elixir
        family=["ادویه", "گل"], occasion=["مراسم رسمی", "مهمانی", "محل کار"],
        season=["پاییز", "زمستان"], personality=["جسور", "کلاسیک"],
        longevity="خیلی بلند",
        notes={"top": "گلابی، هل، فلفل سیاه",
               "heart": "شیپ، اسانس گل، یاس",
               "base": "چوب صندل، ونیل، شی"})),
    ("ساواج الکسیر", dict(
        family=["ادویه", "گل"], occasion=["مراسم رسمی", "مهمانی", "محل کار"],
        season=["پاییز", "زمستان"], personality=["جسور", "کلاسیک"],
        longevity="خیلی بلند",
        notes={"top": "گلابی، هل، فلفل سیاه",
               "heart": "شیپ، اسانس گل، یاس",
               "base": "چوب صندل، ونیل، شی"})),
    ("Sauvage Parfum", dict(
        family=["آکواتیک", "ادویه"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["پاییز", "بهار"], personality=["مدرن", "کلاسیک"],
        longevity="بلند",
        notes={"top": "برگاموت، فلفل",
               "heart": "اسانس زعفران، اسانس جیریان، لاوندر",
               "base": "چوب سدر، آمبrette، چرم"})),
    ("ساواج دیور", dict(
        family=["آکواتیک", "ادویه"], occasion=["روزمره", "محل کار", "ورزشی"],
        season=["بهار", "تابستان"], personality=["مدرن", "جسور"],
        longevity="متوسط",
        notes={"top": "برگاموت، برگاموت کالابرسی",
               "heart": "فلفل، اسانس زعفران، لاوندر",
               "base": "چوب سدر، چرم، جیریان"})),
    ("ساواج", dict(
        family=["آکواتیک", "ادویه"], occasion=["روزمره", "محل کار", "ورزشی"],
        season=["بهار", "تابستان"], personality=["مدرن", "جسور"],
        longevity="متوسط",
        notes={"top": "برگاموت، فلفل",
               "heart": "اسانس زعفران، لاوندر",
               "base": "چوب سدر، چرم"})),

    # -- Bleu de Chanel -----------------------------------------------------
    ("Blue De Chanel", dict(
        family=["آکواتیک", "گل", "ادویه"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "مؤدب", "مدرن"],
        longevity="بلند",
        notes={"top": "لیمو، برگاموت",
               "heart": "اسانس گلابی، ژربرگ",
               "base": "چوب صندل، مشک"})),
    ("Bleu de Chanel", dict(
        family=["آکواتیک", "گل", "ادویه"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "مؤدب", "مدرن"],
        longevity="بلند",
        notes={"top": "لیمو، برگاموت",
               "heart": "اسانس گلابی، ژربرگ",
               "base": "چوب صندل، مشک"})),
    ("Bleu De Chanel", dict(
        family=["آکواتیک", "گل", "ادویه"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "مؤدب", "مدرن"],
        longevity="بلند",
        notes={"top": "لیمو، برگاموت",
               "heart": "اسانس گلابی، ژربرگ",
               "base": "چوب صندل، مشک"})),
    ("CH POUR HOMME", dict(
        family=["چوب", "گل", "آکواتیک"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["پاییز", "بهار"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "لیمو، برگاموت، گشنیز",
               "heart": "اسانس گلابی، گل بنفشه",
               "base": "چوب صندل، چرم، مشک"})),
    ("بلو دی شنل", dict(
        family=["آکواتیک", "گل", "ادویه"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "مؤدب", "مدرن"],
        longevity="بلند",
        notes={"top": "لیمو، برگاموت",
               "heart": "اسانس گلابی، ژربرگ",
               "base": "چوب صندل، مشک"})),
    ("بلو شنل", dict(
        family=["آکواتیک", "گل", "ادویه"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "مؤدب", "مدرن"],
        longevity="بلند",
        notes={"top": "لیمو، برگاموت",
               "heart": "اسانس گلابی، ژربرگ",
               "base": "چوب صندل، مشک"})),

    # -- other single-model rules ------------------------------------------
    ("دیزایر بلو", dict(                       # Diesel Desire Blue
        family=["آکواتیک", "میوه"], occasion=["روزمره", "ورزشی", "سفر"],
        season=["بهار", "تابستان"], personality=["مدرن", "آرام"],
        longevity="متوسط",
        notes={"top": "لیمو، برگاموت، مرکبات",
               "heart": "نرگس، دریا",
               "base": "مشک، نمک دریا"})),
    ("آلفرد دانهیل دیزایر", dict(
        family=["گل", "میوه"], occasion=["روزمره", "مهمانی", "محل کار"],
        season=["بهار", "تابستان"], personality=["کلاسیک", "مؤدب"],
        longevity="متوسط",
        notes={"top": "گلابی، گردنگ، نارنجی",
               "heart": "یاس، گل بنفشه",
               "base": "مشک، پاتریزی"})),
    ("دانهیل دیزایر بلو", dict(
        family=["آکواتیک", "میوه"], occasion=["روزمره", "ورزشی", "سفر"],
        season=["بهار", "تابستان"], personality=["مدرن", "آرام"],
        longevity="متوسط",
        notes={"top": "لیمو، مرکبات",
               "heart": "دریا، نرگس",
               "base": "مشک، چوب"})),
    ("اینوکتوس CH دانهیل", dict(               # Dunhill Invictus CH
        family=["چوب", "ادویه"], occasion=["محل کار", "مراسم رسمی", "مهمانی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "جسور"],
        longevity="بلند",
        notes={"top": "لیمو، فلفل",
               "heart": "دارچین، رز",
               "base": "چوب صندل، چرم"})),
    ("اینوکتوس اسپرت", dict(
        family=["آکواتیک", "میوه"], occasion=["ورزشی", "سفر", "روزمره"],
        season=["بهار", "تابستان"], personality=["مدرن", "شیطون"],
        longevity="متوسط",
        notes={"top": "گریپ‌فروت، مرکبات",
               "heart": "دریا، نعناع",
               "base": "مشک، چوب"})),
    ("اینوکتوس", dict(                           # Paco Invictus
        family=["آکواتیک", "گل", "ادویه"], occasion=["روزمره", "ورزشی", "محل کار", "سفر"],
        season=["بهار", "تابستان", "پاییز"], personality=["جسور", "مدرن"],
        longevity="بلند",
        notes={"top": "لیمو، گریپ‌فروت، برگاموت",
               "heart": "گلابی، اسانس لیتیوم",
               "base": "چوب صندل، مشک"})),
    ("Paco Invictus", dict(
        family=["آکواتیک", "گل", "ادویه"], occasion=["روزمره", "ورزشی", "محل کار", "سفر"],
        season=["بهار", "تابستان", "پاییز"], personality=["جسور", "مدرن"],
        longevity="بلند",
        notes={"top": "لیمو، گریپ‌فروت",
               "heart": "گلابی، اسانس لیتیوم",
               "base": "چوب صندل، مشک"})),
    ("اینوکتوس پاکو رابان", dict(
        family=["آکواتیک", "گل", "ادویه"], occasion=["روزمره", "ورزشی", "محل کار"],
        season=["بهار", "تابستان"], personality=["جسور", "مدرن"],
        longevity="بلند",
        notes={"top": "لیمو، گریپ‌فروت",
               "heart": "گلابی",
               "base": "چوب صندل، مشک"})),

    ("کول واتر", dict(                            # Davidoff Cool Water
        family=["آکواتیک"], occasion=["روزمره", "محل کار", "ورزشی", "سفر"],
        season=["بهار", "تابستان"], personality=["آرام", "مدرن", "کلاسیک"],
        longevity="متوسط",
        notes={"top": "لیمو، برگاموت، نعناع",
               "heart": "جودی، دریا",
               "base": "مشک، چوب"})),
    ("دایویدف کول واتر", dict(
        family=["آکواتیک"], occasion=["روزمره", "محل کار", "ورزشی"],
        season=["بهار", "تابستان"], personality=["آرام", "مدرن"],
        longevity="متوسط",
        notes={"top": "لیمو، برگاموت",
               "heart": "جودی، دریا",
               "base": "مشک، چوب"})),
    ("Cool Water", dict(
        family=["آکواتیک"], occasion=["روزمره", "محل کار", "ورزشی", "سفر"],
        season=["بهار", "تابستان"], personality=["آرام", "مدرن", "کلاسیک"],
        longevity="متوسط",
        notes={"top": "لیمو، برگاموت، نعناع",
               "heart": "جودی، دریا",
               "base": "مشک، چوب"})),

    ("Aventus", dict(                              # Creed Aventus
        family=["میوه", "چوب", "آکواتیک"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["مدرن", "جسور", "مؤدب"],
        longevity="بلند",
        notes={"top": "برگاموت، توت‌فرنگی، آناناس",
               "heart": "بنفشه، یاس، چوب روز",
               "base": "مشک، چوب صندل"})),
    ("Creed Aventus", dict(
        family=["میوه", "چوب", "آکواتیک"], occasion=["محل کار", "مراسم رسمی"],
        season=["بهار", "تابستان", "پاییز"], personality=["مدرن", "جسور"],
        longevity="بلند",
        notes={"top": "برگاموت، توت‌فرنگی",
               "heart": "بنفشه، یاس",
               "base": "مشک، چوب صندل"})),

    ("Mont Blanc Legend", dict(
        family=["چوب", "میوه", "شرقی"], occasion=["محل کار", "مراسم رسمی", "مهمانی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب", "جسور"],
        longevity="بلند",
        notes={"top": "گریپ‌فروت، فلفل",
               "heart": "اسانس گل، دارچین",
               "base": "ونیل، چوب سدر، تنباکو"})),
    ("LALIQUE NOIR EXTREME", dict(
        family=["چوب", "شرقی", "ادویه"], occasion=["مراسم رسمی", "مهمانی", "محل کار"],
        season=["پاییز", "زمستان"], personality=["جسور", "کلاسیک"],
        longevity="بلند",
        notes={"top": "هل، فلفل",
               "heart": "دارچین، گل",
               "base": "چوب تیره، وتیور، چرم"})),
    ("لالیک بلک", dict(
        family=["چوب", "شرقی"], occasion=["مراسم رسمی", "مهمانی"],
        season=["پاییز", "زمستان"], personality=["جسور", "کلاسیک"],
        longevity="بلند",
        notes={"top": "فلفل، هل",
               "heart": "گل، چوب",
               "base": "چرم، وتیور"})),
    ("Dior Homme Intense", dict(
        family=["چوب", "میوه"], occasion=["محل کار", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "لیمو، هل",
               "heart": "اسانس زعفران، گل، برگ بنفشه",
               "base": "چوب سدر، چرم"})),
    ("دیور هوم اینتنس", dict(
        family=["چوب", "میوه"], occasion=["محل کار", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "لیمو، هل",
               "heart": "زعفران، گل",
               "base": "چوب سدر، چرم"})),
    ("فارنهایت", dict(
        family=["چوب", "شرقی", "میوه"], occasion=["مراسم رسمی", "مهمانی", "محل کار"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "لیمو، فلفل",
               "heart": "گلابی، یاس",
               "base": "ونیل، چرم"})),
    ("Guilty Dior", dict(
        family=["شرقی", "میوه"], occasion=["مهمانی", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["شیطون", "جسور"],
        longevity="بلند",
        notes={"top": "گلابی، نارنجی",
               "heart": "یاس، زعفران",
               "base": "ونیل، پاتریزی"})),
    ("حیاتی", dict(
        family=["چوب", "شرقی"], occasion=["محل کار", "مهمانی"],
        season=["پاییز", "زمستان"], personality=["مدرن", "کلاسیک"],
        longevity="بلند",
        notes={"top": "فلفل، مرکبات",
               "heart": "گل، چوب",
               "base": "ونیل، چرم"})),
    ("خمره قهوه", dict(
        family=["شرقی", "میوه"], occasion=["مهمانی", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["جسور", "کلاسیک"],
        longevity="بلند",
        notes={"top": "قهوه، وانیل",
               "heart": "دارچین، گل",
               "base": "چوب، مشک"})),
    ("LALIQUE NOIR", dict(
        family=["چوب", "شرقی"], occasion=["مراسم رسمی", "مهمانی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک"],
        longevity="بلند",
        notes={"top": "هل، فلفل",
               "heart": "گل",
               "base": "چرم، وتیور"})),
    ("تش هرمس", dict(
        family=["چوب", "شرقی", "ادویه"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "فلفل، ژنیپ",
               "heart": "اسانس زعفران، گل",
               "base": "چوب سرو، چرم، وتیور"})),
    ("تق هرمس", dict(
        family=["چوب", "شرقی", "ادویه"], occasion=["محل کار", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "فلفل، ژنیپ",
               "heart": "زعفران، گل",
               "base": "چوب سرو، چرم"})),

    ("Encre Noire", dict(
        family=["چوب", "شرقی"], occasion=["محل کار", "روزمره", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "جسور"],
        longevity="بلند",
        notes={"top": "برگاموت، فلفل",
               "heart": "اسانس آویشن، گل",
               "base": "چوب سرو، وتیور، چرم"})),
    ("Cyrus", dict(
        family=["چوب", "شرقی"], occasion=["محل کار", "مهمانی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک"],
        longevity="بلند",
        notes={"top": "فلفل، ژنیپ",
               "heart": "اسانس صندل، گل",
               "base": "چوب، مشک"})),
    ("Tom Ford Tobacco Vanille", dict(
        family=["شرقی", "میوه"], occasion=["مهمانی", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["جسور", "کلاسیک"],
        longevity="خیلی بلند",
        notes={"top": "برگاموت، نارنجی",
               "heart": "اسانس تنباکو، وانیل",
               "base": "چوب سرو، چرم، تنباکو"})),
    ("تام فورد توباکو وانیل", dict(
        family=["شرقی", "میوه"], occasion=["مهمانی", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["جسور"],
        longevity="خیلی بلند",
        notes={"top": "برگاموت، نارنجی",
               "heart": "تنباکو، وانیل",
               "base": "چوب سرو، چرم"})),
    ("بایلندو ایو سن لورن", dict(
        family=["آکواتیک", "میوه"], occasion=["روزمره", "محل کار", "ورزشی"],
        season=["بهار", "تابستان"], personality=["مدرن", "آرام"],
        longevity="متوسط",
        notes={"top": "لیمو، نارنجی",
               "heart": "اسانس زیتون، مریمی",
               "base": "چوب سپر، مشک"})),
    ("ایو سن لورن وای", dict(
        family=["آکواتیک", "میوه"], occasion=["روزمره", "محل کار"],
        season=["بهار", "تابستان"], personality=["مدرن", "آرام"],
        longevity="متوسط",
        notes={"top": "لیمو، مرکبات",
               "heart": "اسانس زیتون، گل",
               "base": "چوب، مشک"})),
    ("L\'Eau d\'Issey", dict(
        family=["آکواتیک", "گل"], occasion=["روزمره", "محل کار", "ورزشی"],
        season=["بهار", "تابستان"], personality=["آرام", "کلاسیک", "مدرن"],
        longevity="متوسط",
        notes={"top": "لیمو، برگاموت",
               "heart": "نرگس، گل",
               "base": "چوب، مشک"})),
    ("Val d\'Orne", dict(
        family=["چوب", "آکواتیک"], occasion=["روزمره", "محل کار"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "آرام"],
        longevity="متوسط",
        notes={"top": "مرکبات، نعناع",
               "heart": "اسانس گل، چوب",
               "base": "چوب، مشک"})),
    ("مگاماره اورتو پاریسی", dict(
        family=["چوب", "آکواتیک"], occasion=["روزمره", "محل کار"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "آرام"],
        longevity="متوسط",
        notes={"top": "مرکبات، نعناع",
               "heart": "گل، چوب",
               "base": "چوب، مشک"})),
    ("Honig", dict(                                   # Iranian honey-toned house note
        family=["میوه", "گل"], occasion=["روزمره", "مهمانی", "محل کار"],
        season=["بهار", "تابستان"], personality=["مدرن", "شیطون"],
        longevity="متوسط",
        notes={"top": "عسل، گیلاس",
               "heart": "گل، یاس",
               "base": "ونیل، مشک"})),
    ("ناین پی ام", dict(                            # NaN / Nine PM style
        family=["شرقی", "میوه"], occasion=["مهمانی", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["جسور", "شیطون"],
        longevity="بلند",
        notes={"top": "فلفل، مرکبات",
               "heart": "گل، دارچین",
               "base": "ونیل، چرم"})),
    ("ناین پی", dict(
        family=["شرقی", "میوه"], occasion=["مهمانی", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["جسور"],
        longevity="بلند",
        notes={"top": "فلفل، مرکبات",
               "heart": "گل، دارچین",
               "base": "ونیل، چرم"})),
    ("Pure Black", dict(
        family=["چوب", "ادویه"], occasion=["مهمانی", "محل کار", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["جسور", "کلاسیک"],
        longevity="بلند",
        notes={"top": "فلفل، هل",
               "heart": "دارچین، گل",
               "base": "چرم، وتیور"})),
    ("Dunhill Desire", dict(
        family=["چوب", "گل"], occasion=["روزمره", "محل کار", "مهمانی"],
        season=["بهار", "تابستان", "پاییز"], personality=["مدرن", "مؤدب"],
        longevity="متوسط",
        notes={"top": "برگاموت، مرکبات",
               "heart": "گل، جیریان",
               "base": "چوب، مشک"})),
    ("Dunhill", dict(
        family=["چوب", "گل"], occasion=["روزمره", "محل کار"],
        season=["بهار", "تابستان"], personality=["مدرن", "مؤدب"],
        longevity="متوسط",
        notes={"top": "مرکبات",
               "heart": "گل",
               "base": "چوب، مشک"})),
    ("DUNHILL BLUE", dict(
        family=["آکواتیک", "گل"], occasion=["روزمره", "محل کار", "ورزشی"],
        season=["بهار", "تابستان"], personality=["مدرن", "آرام"],
        longevity="متوسط",
        notes={"top": "برگاموت، لیمو",
               "heart": "اسانس گل، نرگس",
               "base": "چوب، مشک"})),
    ("Club De Nuit Intense", dict(
        family=["چوب", "شرقی"], occasion=["مراسم رسمی", "مهمانی", "محل کار"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "فلفل، لیمو",
               "heart": "اسانس گل، قی Hahn",
               "base": "چوب سرو، چرم"})),
    ("Infinity Silver", dict(
        family=["آکواتیک", "چوب"], occasion=["محل کار", "روزمره", "مهمانی"],
        season=["بهار", "تابستان", "پاییز"], personality=["مدرن", "مؤدب"],
        longevity="متوسط",
        notes={"top": "لیمو، مرکبات",
               "heart": "اسانس گل، یاس",
               "base": "چوب سرو، مشک"})),
    ("Pure Red", dict(
        family=["میوه", "شرقی"], occasion=["مهمانی", "روزمره"],
        season=["پاییز", "زمستان"], personality=["شیطون", "جسور"],
        longevity="متوسط",
        notes={"top": "توت، گیلاس",
               "heart": "گل، یاس",
               "base": "ونیل، مشک"})),
    ("Central Park", dict(                            # Nishat / generic eastern floral
        family=["گل", "میوه"], occasion=["مهمانی", "روزمره", "مراسم رسمی"],
        season=["بهار", "تابستان"], personality=["مؤدب", "مدرن"],
        longevity="متوسط",
        notes={"top": "گلابی، مرکبات",
               "heart": "گل، یاس",
               "base": "مشک، چوب"})),
    ("Louboutin", dict(
        family=["چوب", "شرقی"], occasion=["مهمانی", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["جسور"],
        longevity="بلند",
        notes={"top": "فلفل، هل",
               "heart": "گل، دارچین",
               "base": "چرم، وتیور"})),
    ("Victor", dict(
        family=["چوب", "میوه"], occasion=["محل کار", "روزمره", "مهمانی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="متوسط",
        notes={"top": "لیمو، فلفل",
               "heart": "اسانس گل، دارچین",
               "base": "چوب سدر، چرم"})),
    ("Salvador", dict(
        family=["شرقی", "چوب"], occasion=["مهمانی", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["جسور", "شیطون"],
        longevity="بلند",
        notes={"top": "فلفل، مرکبات",
               "heart": "دارچین، گل",
               "base": "ونیل، چرم"})),
    ("Open", dict(
        family=["شرقی", "ادویه"], occasion=["مهمانی", "روزمره"],
        season=["پاییز", "زمستان"], personality=["جسور", "شیطون"],
        longevity="بلند",
        notes={"top": "فلفل، مرکبات",
               "heart": "دارچین، گل",
               "base": "ونیل، چرم"})),
    ("Morgan de toi", dict(                           # Halton / warm sweet
        family=["شرقی", "میوه"], occasion=["مهمانی", "روزمره", "محل کار"],
        season=["پاییز", "زمستان"], personality=["مؤدب", "مدرن"],
        longevity="متوسط",
        notes={"top": "توت، نارنجی",
               "heart": "یاس، وانیل",
               "base": "مشک، چوب"})),
    ("Killian", dict(                                 # Angels / Angels Share style
        family=["شرقی", "میوه", "می"], occasion=["مهمانی", "روزمره"],
        season=["پاییز", "زمستان"], personality=["جسور", "شیطون"],
        longevity="متوسط",
        notes={"top": "قورت، پرتقال تلخ",
               "heart": "اسانس وانیل، گل",
               "base": "پچولی، مشک"})),
    ("نی پی ام", dict(
        family=["شرقی", "میوه"], occasion=["مهمانی", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["جسور"],
        longevity="بلند",
        notes={"top": "فلفل، مرکبات",
               "heart": "گل، دارچین",
               "base": "ونیل، چرم"})),
    ("Ernest", dict(                                 # Chanel No.5
        family=["گل", "ادویه"], occasion=["مراسم رسمی", "مهمانی"],
        season=["بهار", "پاییز"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "مرکبات، یاس",
               "heart": "گل سفید، رز",
               "base": "یلنگ یلانگ، وانیل"})),
    ("شیل شماره ۵", dict(
        family=["گل", "ادویه"], occasion=["مراسم رسمی", "مهمانی"],
        season=["بهار", "پاییز"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "مرکبات، یاس",
               "heart": "گل سفید، رز",
               "base": "یلنگ یلانگ، وانیل"})),
    ("COCO", dict(                                    # Chanel Coco Mademoiselle style
        family=["چوب", "شرقی"], occasion=["روزمره", "محل کار", "مهمانی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "مرکبات، انار",
               "heart": "گل، شیپ",
               "base": "چوب صندل، چرم"})),
    ("Bleue de Chanel EDP", dict(
        family=["آکواتیک", "گل", "ادویه"], occasion=["محل کار", "مراسم رسمی"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "لیمو، برگاموت",
               "heart": "گلابی، ژربرگ",
               "base": "چوب صندل"})),
    ("AMARANTE", dict(                                 # Artistry Amara
        family=["گل", "میوه"], occasion=["روزمره", "مهمانی"],
        season=["بهار", "تابستان"], personality=["مؤدب", "مدرن"],
        longevity="متوسط",
        notes={"top": "لیمو، گلابی",
               "heart": "یاس، گل",
               "base": "مشک، چوب"})),
    ("Aquamarine", dict(                              # Davidoff Cool Water Woman style
        family=["آکواتیک", "گل"], occasion=["روزمره", "محل کار"],
        season=["بهار", "تابستان"], personality=["آرام", "مدرن"],
        longevity="متوسط",
        notes={"top": "لیمو، نارنجی",
               "heart": "نرگس، جودی",
               "base": "مشک، چوب"})),
    ("Johnson", dict(                                  # Davidoff Champion style
        family=["آکواتیک", "گل"], occasion=["ورزشی", "روزمره", "محل کار"],
        season=["بهار", "تابستان"], personality=["مدرن", "شیطون"],
        longevity="متوسط",
        notes={"top": "لیمو، نعناع",
               "heart": "گل، جودی",
               "base": "چوب، مشک"})),
    ("Creed Aventus", dict(
        family=["میوه", "چوب"], occasion=["محل کار", "مراسم رسمی"],
        season=["بهار", "تابستان", "پاییز"], personality=["مدرن", "جسور"],
        longevity="بلند",
        notes={"top": "توت‌فرنگی، آناناس",
               "heart": "بنفشه، یاس",
               "base": "مشک، چوب صندل"})),
    ("Givenchy Gentleman", dict(
        family=["چوب", "میوه"], occasion=["محل کار", "روزمره"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="متوسط",
        notes={"top": "برگاموت، گلابی",
               "heart": "اسانس زیتون، یاس",
               "base": "چوب"})),
    ("Gentleman", dict(
        family=["چوب", "میوه"], occasion=["محل کار", "روزمره"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="متوسط",
        notes={"top": "برگاموت، گلابی",
               "heart": "زیتون، یاس",
               "base": "چوب"})),
    ("Eternity", dict(                                 # CK Eternity for Men
        family=["آکواتیک", "چوب"], occasion=["محل کار", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "آرام", "مؤدب"],
        longevity="متوسط",
        notes={"top": "لیمو، برگاموت",
               "heart": "اسانس خزامیا، یاس",
               "base": "چوب سرو، مشک"})),
    ("attrask", dict(                                   # CK/Eternity attrask style
        family=["آکواتیک", "چوب"], occasion=["محل کار", "روزمره"],
        season=["بهار", "تابستان"], personality=["کلاسیک", "آرام"],
        longevity="متوسط",
        notes={"top": "لیمو، مرکبات",
               "heart": "گل",
               "base": "چوب، مشک"})),
    ("Bloom", dict(                                     # Gucci Bloom style
        family=["گل", "میوه"], occasion=["روزمره", "مهمانی", "محل کار"],
        season=["بهار", "تابستان"], personality=["مؤدب", "مدرن", "کلاسیک"],
        longevity="متوسط",
        notes={"top": "گلابی، گل بنفشه",
               "heart": "یاس، گل",
               "base": "مشک، چوب"})),
    ("Eros", dict(                                       # Versace Eros
        family=["شرقی", "میوه"], occasion=["مهمانی", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["جسور", "شیطون"],
        longevity="متوسط",
        notes={"top": "نارنجی، لیمو، اسانس نعناع",
               "heart": "گل، وانیل",
               "base": "چوب، مشک"})),
    ("Eau de Toilette", dict(
        family=["آکواتیک"], occasion=["روزمره", "محل کار", "ورزشی"],
        season=["بهار", "تابستان"], personality=["مدرن", "آرام"],
        longevity="متوسط",
        notes={"top": "لیمو، برگاموت",
               "heart": "گل",
               "base": "چوب، مشک"})),
    ("Jour", dict(                                      # Dior Homme-ish Eau de Jour
        family=["چوب", "گل"], occasion=["محل کار", "روزمره"],
        season=["بهار", "تابستان"], personality=["کلاسیک", "مؤدب"],
        longevity="متوسط",
        notes={"top": "لیمو، اسانس نعناع",
               "heart": "گل",
               "base": "چوب سرو، مشک"})),
    ("096", dict(                                       # Skinnydive / 020 style
        family=["آکواتیک", "چوب"], occasion=["ورزشی", "روزمره", "محل کار"],
        season=["بهار", "تابستان"], personality=["مدرن", "شیطون"],
        longevity="متوسط",
        notes={"top": "لیمو، مرکبات",
               "heart": "دریا",
               "base": "چوب، مشک"})),
    ("Zara Wood", dict(                                  # Zara Wood / warm woody
        family=["چوب", "شرقی"], occasion=["روزمره", "محل کار", "مهمانی"],
        season=["پاییز", "زمستان"], personality=["مدرن", "مؤدب"],
        longevity="متوسط",
        notes={"top": "برگاموت، فلفل",
               "heart": "دارچین، گل",
               "base": "چوب سدر، ونیل"})),
    ("Love", dict(                                      # Lancôme La Vie est Belle
        family=["میوه", "گل"], occasion=["مهمانی", "روزمره", "مراسم رسمی"],
        season=["بهار", "تابستان"], personality=["مؤدب", "مدرن"],
        longevity="متوسط",
        notes={"top": "توت‌فرنگی، گلابی",
               "heart": "یاس، گل",
               "base": "ونیل، مشک"})),
    ("La vie", dict(
        family=["میوه", "گل"], occasion=["مهمانی", "روزمره"],
        season=["بهار", "تابستان"], personality=["مؤدب"],
        longevity="متوسط",
        notes={"top": "توت‌فرنگی",
               "heart": "یاس، گل",
               "base": "ونیل"})),
    ("Alexander", dict(                                  # Pendrey Alexander style
        family=["چوب", "شرقی"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="متوسط",
        notes={"top": "برگاموت، فلفل",
               "heart": "اسانس گل، دارچین",
               "base": "چوب سدر، چرم"})),
    ("Greenly", dict(                                    # Delgado Greenly
        family=["آکواتیک", "گل"], occasion=["روزمره", "محل کار"],
        season=["بهار", "تابستان"], personality=["آرام", "مدرن"],
        longevity="متوسط",
        notes={"top": "لیمو، سیب سبز",
               "heart": "گل، یاس",
               "base": "مشک، چوب"})),
    ("Molecule 02", dict(                                 # Molecule 02-ish
        family=["آکواتیک", "چوب"], occasion=["محل کار", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["مدرن", "آرام"],
        longevity="متوسط",
        notes={"top": "لیمو، فلفل",
               "heart": "اسانس صندل",
               "base": "مشک، چوب"})),
    ("Athos", dict(                                        # Franck Olivier Athos
        family=["آکواتیک", "گل"], occasion=["روزمره", "محل کار"],
        season=["بهار", "تابستان"], personality=["کلاسیک", "مؤدب"],
        longevity="متوسط",
        notes={"top": "لیمو، برگاموت",
               "heart": "اسانس رز، یاس",
               "base": "چوب سپر، مشک"})),
    ("Concordia", dict(                                   # Myrurgia Concordia
        family=["آکواتیک", "گل"], occasion=["روزمره", "محل کار"],
        season=["بهار", "تابستان"], personality=["آرام", "مدرن"],
        longevity="متوسط",
        notes={"top": "لیمو، مرکبات",
               "heart": "گل، یاس",
               "base": "چوب، مشک"})),
    ("Amazone", dict(                                     # Hermès Terre d'Hermès-ish Amazone
        family=["چوب", "گل"], occasion=["محل کار", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["مؤدب", "کلاسیک"],
        longevity="متوسط",
        notes={"top": "گریپ‌فروت، مرکبات",
               "heart": "اسانس مریمی، گل",
               "base": "چوب سپر، مشک"})),
    ("TD", dict(                                            # Terre d'Hermès
        family=["چوب", "گل"], occasion=["محل کار", "روزمره", "مهمانی"],
        season=["بهار", "تابستان", "پاییز"], personality=["مؤدب", "کلاسیک"],
        longevity="متوسط",
        notes={"top": "گریپ‌فروت، مرکبات",
               "heart": "اسانس مریمی",
               "base": "چوب سپر، مشک"})),
    ("مسک", dict(                                           # generic musk house
        family=["گل", "آکواتیک"], occasion=["روزمره", "محل کار", "مهمانی"],
        season=["بهار", "تابستان"], personality=["مؤدب", "آرام"],
        longevity="متوسط",
        notes={"top": "گلابی، لیمو",
               "heart": "یاس، گل",
               "base": "مشک، چوب"})),

    # -- second pass: titles whose model name differs from the scent name ----
    ("ایفوریا مدل اونتیوس", dict(                     # Invictus
        family=["آکواتیک", "گل", "ادویه"], occasion=["روزمره", "ورزشی", "محل کار"],
        season=["بهار", "تابستان"], personality=["جسور", "مدرن"],
        longevity="بلند",
        notes={"top": "لیمو، گریپ‌فروت",
               "heart": "گلابی، اسانس لیتیوم",
               "base": "چوب صندل، مشک"})),
    ("پاکو رابان", dict(                                  # Paco Invictus
        family=["آکواتیک", "گل", "ادویه"], occasion=["روزمره", "ورزشی", "محل کار"],
        season=["بهار", "تابستان"], personality=["جسور", "مدرن"],
        longevity="بلند",
        notes={"top": "لیمو، گریپ‌فروت",
               "heart": "گلابی",
               "base": "چوب صندل، مشک"})),
    ("ژاورو مدل اونتوس", dict(
        family=["آکواتیک", "گل", "ادویه"], occasion=["روزمره", "ورزشی"],
        season=["بهار", "تابستان"], personality=["جسور", "مدرن"],
        longevity="بلند",
        notes={"top": "لیمو، گریپ‌فروت",
               "heart": "گلابی",
               "base": "چوب صندل، مشک"})),
    ("AQVA BVLGARI", dict(                                 # Aqva Amara
        family=["آکواتیک", "میوه"], occasion=["روزمره", "ورزشی", "سفر"],
        season=["بهار", "تابستان"], personality=["مدرن", "آرام"],
        longevity="متوسط",
        notes={"top": "لیمو، گریپ‌فروت، مرکبات",
               "heart": "اسانس مریمی، نعناع",
               "base": "چوب، مشک"})),
    ("اکوا دی جیو", dict(                                # Armani Aqua di Gio
        family=["آکواتیک", "میوه"], occasion=["روزمره", "محل کار", "ورزشی"],
        season=["بهار", "تابستان"], personality=["مدرن", "آرام"],
        longevity="متوسط",
        notes={"top": "لیمو، پرتقال تلخ",
               "heart": "اسانس مریمی، نعناع",
               "base": "چوب سپر، مشک"})),
    ("گرینلی", dict(
        family=["آکواتیک", "گل"], occasion=["روزمره", "محل کار"],
        season=["بهار", "تابستان"], personality=["آرام", "مدرن"],
        longevity="متوسط",
        notes={"top": "لیمو، سیب سبز",
               "heart": "گل، یاس",
               "base": "مشک، چوب"})),
    ("الکساندر", dict(                                    # Pendrey Alexander
        family=["چوب", "شرقی"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="متوسط",
        notes={"top": "برگاموت، فلفل",
               "heart": "اسانس گل، دارچین",
               "base": "چوب سدر، چرم"})),
    ("مدل اینتنس", dict(                                 # Dior Homme Intense
        family=["چوب", "میوه"], occasion=["محل کار", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "لیمو، هل",
               "heart": "زعفران، گل",
               "base": "چوب سدر، چرم"})),
    ("اینتنس مدل Dior", dict(                             # same, brand written after
        family=["چوب", "میوه"], occasion=["محل کار", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "لیمو، هل",
               "heart": "زعفران، گل",
               "base": "چوب سدر، چرم"})),
    ("کریداونتوس", dict(                                 # Creed Aventus
        family=["میوه", "چوب", "آکواتیک"], occasion=["محل کار", "مراسم رسمی"],
        season=["بهار", "تابستان", "پاییز"], personality=["مدرن", "جسور"],
        longevity="بلند",
        notes={"top": "برگاموت، توت‌فرنگی",
               "heart": "بنفشه، یاس",
               "base": "مشک، چوب صندل"})),
    ("ژوپ", dict(                                          # Gravity / Joup
        family=["آکواتیک", "چوب", "ادویه"], occasion=["محل کار", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["مدرن", "آرام"],
        longevity="متوسط",
        notes={"top": "لیمو، فلفل",
               "heart": "اسانس صندل، دریا",
               "base": "مشک، چوب"})),
    ("نایس پاپت", dict(                                   # Versace Eros
        family=["شرقی", "میوه"], occasion=["مهمانی", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["جسور", "شیطون"],
        longevity="متوسط",
        notes={"top": "نارنجی، لیمو",
               "heart": "گل، وانیل",
               "base": "چوب، مشک"})),
    ("مولکول 02", dict(
        family=["آکواتیک", "چوب"], occasion=["محل کار", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["مدرن", "آرام"],
        longevity="متوسط",
        notes={"top": "لیمو، فلفل",
               "heart": "اسانس صندل",
               "base": "مشک، چوب"})),
    ("انجلز شیر", dict(                                    # Angels Share
        family=["شرقی", "میوه"], occasion=["مهمانی", "روزمره"],
        season=["پاییز", "زمستان"], personality=["جسور", "شیطون"],
        longevity="متوسط",
        notes={"top": "فلفل، پرتقال تلخ",
               "heart": "اسانس تنباکو، وانیل",
               "base": "پچولی، مشک"})),
    ("گرموشا", dict(                                       # Cerruti 1881 / Ermenegildo Zegna Ermenage
        family=["چوب", "شرقی", "میوه"], occasion=["محل کار", "مهمانی", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="متوسط",
        notes={"top": "برگاموت، فلفل",
               "heart": "دارچین، گل",
               "base": "چوب سدر، ونیل"})),
    ("بلوشنل", dict(                                       # Chanel Bleu
        family=["آکواتیک", "گل", "ادویه"], occasion=["محل کار", "مراسم رسمی", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "لیمو، برگاموت",
               "heart": "گلابی، ژربرگ",
               "base": "چوب صندل"})),
    ("د مارلی", dict(                                      # Halton / warm sweet oriental
        family=["شرقی", "میوه"], occasion=["مهمانی", "روزمره", "محل کار"],
        season=["پاییز", "زمستان"], personality=["مؤدب", "مدرن"],
        longevity="متوسط",
        notes={"top": "توت، نارنجی",
               "heart": "یاس، وانیل",
               "base": "مشک، چوب"})),
    ("OPULENT", dict(                                      # generic warm oriental
        family=["شرقی", "چوب"], occasion=["مهمانی", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["جسور", "کلاسیک"],
        longevity="متوسط",
        notes={"top": "فلفل، هل",
               "heart": "دارچین، گل",
               "base": "ونیل، چرم"})),
    ("لوکا بوسی", dict(                                    # Luca Bossi Sauvage
        family=["آکواتیک", "ادویه"], occasion=["روزمره", "محل کار", "ورزشی"],
        season=["بهار", "تابستان"], personality=["مدرن", "جسور"],
        longevity="متوسط",
        notes={"top": "برگاموت، فلفل",
               "heart": "اسانس زعفران، لاوندر",
               "base": "چوب سدر"})),
    ("020", dict(
        family=["آکواتیک", "چوب"], occasion=["ورزشی", "روزمره", "محل کار"],
        season=["بهار", "تابستان"], personality=["مدرن", "شیطون"],
        longevity="متوسط",
        notes={"top": "لیمو، مرکبات",
               "heart": "دریا",
               "base": "چوب، مشک"})),
    ("آوو", dict(                                           # Koublo/Avo
        family=["آکواتیک", "گل"], occasion=["روزمره", "محل کار"],
        season=["بهار", "تابستان"], personality=["آرام", "مدرن"],
        longevity="متوسط",
        notes={"top": "لیمو، مرکبات",
               "heart": "گل، یاس",
               "base": "مشک، چوب"})),
    ("شنل اگویست", dict(                                   # Chanel Egoiste
        family=["چوب", "گل", "شرقی"], occasion=["محل کار", "روزمره", "مراسم رسمی"],
        season=["پاییز", "زمستان"], personality=["کلاسیک", "مؤدب"],
        longevity="متوسط",
        notes={"top": "لیمو، نعناع",
               "heart": "اسانس گل، زعفران",
               "base": "چوب صندل، چرم"})),
    ("اسنشیال", dict(                                       # Dunhill Essential
        family=["آکواتیک", "گل"], occasion=["روزمره", "محل کار"],
        season=["بهار", "تابستان"], personality=["آرام", "مدرن"],
        longevity="متوسط",
        notes={"top": "لیمو، برگاموت",
               "heart": "اسانس گل",
               "base": "چوب، مشک"})),

    # -- the ten hand-authored rows ------------------------------------------
    ("اترنیتی", dict(                                      # CK Eternity
        family=["آکواتیک", "چوب"], occasion=["محل کار", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["کلاسیک", "آرام", "مؤدب"],
        longevity="متوسط",
        notes={"top": "لیمو، برگاموت",
               "heart": "اسانس خزامیا، یاس",
               "base": "چوب سرو، مشک"})),
    ("زارا وود", dict(
        family=["چوب", "شرقی"], occasion=["روزمره", "محل کار", "مهمانی"],
        season=["پاییز", "زمستان"], personality=["مدرن", "مؤدب"],
        longevity="متوسط",
        notes={"top": "برگاموت، فلفل",
               "heart": "دارچین، گل",
               "base": "چوب سدر، ونیل"})),
    ("لانکوم لاوی است", dict(                             # La Vie est Belle
        family=["میوه", "گل"], occasion=["مهمانی", "روزمره", "مراسم رسمی"],
        season=["بهار", "تابستان"], personality=["مؤدب", "مدرن"],
        longevity="متوسط",
        notes={"top": "توت‌فرنگی، گلابی",
               "heart": "یاس، گل",
               "base": "ونیل، مشک"})),
    ("گوچی بلوم", dict(
        family=["گل", "میوه"], occasion=["روزمره", "مهمانی", "محل کار"],
        season=["بهار", "تابستان"], personality=["مؤدب", "مدرن", "کلاسیک"],
        longevity="متوسط",
        notes={"top": "گلابی، گل بنفشه",
               "heart": "یاس، گل",
               "base": "مشک، چوب"})),
    ("ورساچه اروس", dict(                                 # Versace Eros
        family=["شرقی", "میوه"], occasion=["مهمانی", "روزمره"],
        season=["بهار", "تابستان", "پاییز"], personality=["جسور", "شیطون"],
        longevity="متوسط",
        notes={"top": "نارنجی، لیمو",
               "heart": "گل، وانیل",
               "base": "چوب، مشک"})),
    ("ترویل وه", dict(                                     # Terre d'Hermès
        family=["چوب", "گل"], occasion=["محل کار", "روزمره", "مهمانی"],
        season=["بهار", "تابستان", "پاییز"], personality=["مؤدب", "کلاسیک"],
        longevity="متوسط",
        notes={"top": "گریپ‌فروت، مرکبات",
               "heart": "اسانس مریمی",
               "base": "چوب سپر، مشک"})),
    ("شنل شماره ۵", dict(                                  # Chanel No. 5
        family=["گل", "ادویه"], occasion=["مراسم رسمی", "مهمانی"],
        season=["بهار", "پاییز"], personality=["کلاسیک", "مؤدب"],
        longevity="بلند",
        notes={"top": "مرکبات، یاس",
               "heart": "گل سفید، رز",
               "base": "یلنگ یلانگ، وانیل"})),
    ("Sauvage Elixir", dict(
        family=["ادویه", "گل"], occasion=["مراسم رسمی", "مهمانی"],
        season=["پاییز", "زمستان"], personality=["جسور", "کلاسیک"],
        longevity="خیلی بلند",
        notes={"top": "گلابی، هل",
               "heart": "زعفران، یاس",
               "base": "چوب صندل، شی"})),
]

# Fallback for a fragrance product whose model we do not have a profile for.
# Deliberately conservative: only the axis the catalogue itself states, so the
# finder still ranks it honestly instead of guessing a family.
DEFAULT = dict(
    family=[], occasion=["روزمره"], season=[], personality=[],
    longevity="متوسط", notes=None,
)

# The fragrance category is an exact discriminator: it holds 110 products, and the
# other 55 are 51 St Ives body/hair care plus 4 gift cards.
FRAGRANCE_CATEGORY = "عطر و ادکلن"

# Gender is stated in the product_category for the non-DIGIKALA rows and in the
# title for the imported ones.
FEMALE_MARKERS = ["زنانه"]


def profile_for(title: str) -> dict:
    # ZWNJ (U+200C) and the Arabic/Persian variants of ی/ک appear inconsistently in
    # these imported titles; normalise before matching so "د شنل" matches the rule
    # authored as "د شنل".
    norm = title.replace("ي", "ی").replace("ك", "ک").replace("‌", " ")
    for needle, profile in PROFILES:
        if needle.replace("ي", "ی").replace("ك", "ک").replace("‌", " ") in norm:
            return dict(profile, matched=needle)
    return dict(DEFAULT, matched=None)


def gender_for(title: str, cats: list) -> str:
    if "زنانه" in title or "زنانه" in cats:
        return "زنانه"
    if "یونیسکس" in cats:
        return "یونیسکس"
    return "مردانه"


def main():
    root = Path(__file__).resolve().parent.parent
    catalog = json.loads(Path("/tmp/detail.json").read_text())

    out = {}
    for row in catalog:
        if FRAGRANCE_CATEGORY not in row["cat"]:
            continue
        sku, title, pid = row["sku"], row["name"], row["id"]
        prof = profile_for(title)
        out[sku] = {
            "id": pid,
            "family": prof["family"],
            "occasion": prof["occasion"],
            "season": prof["season"],
            "personality": prof["personality"],
            "longevity": prof["longevity"],
            "gender": gender_for(title, row["cat"]),
            "notes": prof.get("notes"),
            "matched": prof.get("matched"),
        }

    target = root / "docker" / "finder-profiles.json"
    target.write_text(json.dumps(out, ensure_ascii=False, indent=2, sort_keys=True) + "\n")

    matched = sum(1 for v in out.values() if v["matched"])
    print(f"fragrance products: {len(out)}")
    print(f"with an authored profile: {matched}")
    print(f"fallback only: {len(out) - matched}")
    print(f"written: {target}")
    for sku, v in sorted(out.items()):
        if not v["matched"]:
            print(f"  UNMATCHED {sku} {v['id']}")


if __name__ == "__main__":
    main()
