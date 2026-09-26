# Project relationship — `aroma_store` → `lylyrose`

**Established:** 2026-09-26 · **Applies to:** every doc in this repository

## The one rule

**`aroma_store` is the source of truth. `lylyrose` is downstream of it.**

Any feature, edit, or fix is developed and tested **in `aroma_store` first**. It is only
mirrored into `lylyrose` if `lylyrose` needs it.

```
        aroma_store                          lylyrose
      (source / upstream)                (downstream / dependent)
   aroma-store.vegacodex.ir               lylyrose.ir
     digikala theme                         lylyrose theme
   aroma-store-core 2.3.0                 lylyrose-core 2.3.0
        17 ASC_ classes                     16 ASC_ classes
              │                                   ▲
              │  1. implement + test here          │  2. mirror only if
              └───────────────────────────────────┘     lylyrose needs it
```

`lylyrose` is **not** a rebrand of a finished `aroma_store`, and it is **not** the parent.
The two are parallel deployments of a shared codebase that currently sit one step apart.
Work travels downstream only.

## What the two share

| Concern | `aroma_store` | `lylyrose` |
|---|---|---|
| Live site | `https://aroma-store.vegacodex.ir` | `https://lylyrose.ir` |
| Core plugin | `aroma-store-core` | `lylyrose-core` |
| Storefront theme | `digikala` | `lylyrose` |
| WP / WooCommerce | 7.1 / 11.1.0 | 7.1 / 11.1.0 |
| Test suite | `bash docker/run-tests.sh` | `bash docker/run-tests.sh` |
| Local address | http://localhost:8010 | http://localhost:8020 |
| phpMyAdmin | :8011 | :8021 |
| Admin login | `/secure-login` | `/secure-login` |
| Branded name | آرومالند (Aromaland) | لیلی رز (Lyly Rose) |
| Repo | `github.com/greatkourosh/aroma_store` | `github.com/greatkourosh/lylyrose` |
| Deploy branch | `hosting-ready` (merge master → it) | single `master` branch |

## The rename map

`lylyrose` is mechanically renamed from `aroma_store` throughout. When mirroring a file,
these substitutions are the **only** intended difference:

| In `aroma_store` | In `lylyrose` |
|---|---|
| `aroma-store-core` (plugin slug, text domain, `@package`) | `lylyrose-core` |
| `aroma-store-core.php` (main file) | `lylyrose-core.php` |
| `ASC_` class prefix *(unchanged)* | `ASC_` class prefix *(unchanged)* |
| `digikala` (theme dir, style.css slug) | `lylyrose` |
| `aroma-store` (legacy theme dir) | `aroma-store` *(kept as-is, inactive)* |
| `آرومالند` / `Aromaland` in user-visible strings | `لیلی رز` / `Lyly Rose` |
| `aroma-store.vegacodex.ir` | `lylyrose.ir` |
| `localhost:8010` | `localhost:8020` |

The `ASC_` prefix is deliberately **not** renamed — it keeps the mirrored code diffable.

## Current divergence

`aroma_store` is **ahead**. As of 2026-09-26, one feature exists upstream only:

- **`class-flash-sales.php`** + `page-incredible-offers.php` — the «پیشنهادهای شگفت‌انگیز»
  (Incredible Offers) page, `ASC_Flash_Sales`, built 2026-09-17, still open on browser
  verification. Absent from `lylyrose-core` (16 classes vs upstream's 17).

Also present upstream only: `docker/preview-proxy.py`, `docker/import-product-galleries.php`,
`docker/verify-galleries.php`, and a `hosting-ready` deploy branch.

`lylyrose` has nothing upstream lacks, except the production deploy history itself
(the 2026-09-24 `lylyrose.ir` deploy and its two config fixes).

## How to mirror a change downstream

1. **Implement in `aroma_store`**, on `master`. Add test coverage in
   `docker/run-tests.sh` there. Keep that suite green.
2. **Decide if `lylyrose` needs it.** Not every upstream feature should propagate —
   a store-specific page, a campaign, or a brand-only change stays upstream. This is a
   judgement call; when in doubt, ask rather than mirror by default.
3. **If yes**, port the file into `lylyrose`, apply the rename map above, and make
   `lylyrose`'s suite cover it. A feature that lands in `lylyrose` but never in
   `aroma_store` is a bug in this process.
4. **Never edit `lylyrose`'s copy of shared code to diverge silently.** If `lylyrose`
   genuinely needs different behaviour, that difference belongs in a filter/hook so it
   stays visible rather than becoming a permanent un-mergeable fork.

## Rules that follow from this

- A bug report or feature request against `lylyrose` shared code starts in `aroma_store`.
- A fix landing in `lylyrose` alone means the two have forked; reconcile upstream.
- Test counts, plugin versions, and `ASC_` inventories are expected to match; a mismatch
  is the signal that a mirror step was skipped.
- `lylyrose`'s docs may record `lylyrose` facts, but the shared architecture, the
  feature set, and the gotchas have one home: `aroma_store`'s docs. When they disagree,
  upstream wins and the downstream doc gets corrected.
- Local ports differ: `aroma_store` runs on 8010/8011, `lylyrose` on 8020/8021. They
  were 8080/8081 here until 2026-09-26, when `8080` proved to be a **trap** in this
  host's port map — a stale second Aromaland instance answered there, so a local check
  could silently hit the wrong store. `8020` is not known to collide, but the failure
  mode is silent, so check which store answers (`<title>`) before trusting any local
  check.

## Cross-links

- Upstream handoff: `aroma_store/docs/CONTINUATION.md`
- Downstream handoff: [CONTINUATION.md](CONTINUATION.md)
- Upstream roadmap: `aroma_store/docs/FEATURES_ROADMAP.md`
- Downstream roadmap: [FEATURES_ROADMAP.md](FEATURES_ROADMAP.md)
