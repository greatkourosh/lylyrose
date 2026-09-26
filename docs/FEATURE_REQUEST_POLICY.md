# Feature request policy — `lylyrose` requests route upstream first

**Established:** 2026-09-26 · **Applies to:** every feature request, idea, bug, and
change to shared code in this repository.

This is the operational form of the relationship defined in
[UPSTREAM_RELATIONSHIP.md](UPSTREAM_RELATIONSHIP.md). That doc says *who is upstream*.
This one says *what you do when a feature is requested here*.

## The rule

**`lylyrose` is always behind `aroma_store` on features. So no feature is built here
first.**

A request for this store is **not** a task to implement in this repo. It is a request to
**route upstream**, where it gets built, tested, and proven — and only then mirrored
back.

```
   Request lands here
          │
          ▼
   ┌──────────────┐   Is this store-specific (branding, campaign, config)?
   │  SCREEN IT   │──────────── yes ──────────► build it here, done.
   └──────┬───────┘                               (never needs upstream)
          │ no — shared code
          ▼
   REFER TO aroma_store ──► build + test there
          │                          │
          │                    ┌─────┴─────┐
          │                    │  GREEN?   │
          │                    └─────┬─────┘
          │                       yes │        no → fix upstream first,
          │                          ▼             never port a red test
          │                   mirror to lylyrose
          │                          ▼
          │                   test here too
          │                          ▼
          │                      done
```

## Step by step

1. **Refer the request upstream.** State plainly that `aroma_store` is the source of
   truth and the work starts there. Do not open an implementation in this repo.

2. **Build and test in `aroma_store`.** On `master`, with test coverage added to that
   repo's `docker/run-tests.sh`. That suite must be green.

3. **Bring it back only if green.** A feature whose upstream suite is red does not get
   mirrored — porting a failing change is how both repos end up broken.

4. **Mirror into `lylyrose`.** Apply the rename map
   ([UPSTREAM_RELATIONSHIP.md](UPSTREAM_RELATIONSHIP.md)): `aroma-store-core` →
   `lylyrose-core`, `digikala` → `lylyrose`, آرومالند → لیلی رز, ports 8010 → 8080.
   The `ASC_` prefix is unchanged.

5. **Test here too.** `bash docker/run-tests.sh` in this repo must stay green.

6. **If it is store-specific, skip upstream.** A brand-only string, a one-off campaign
   page, or a host configuration change is legitimately downstream-only — mirroring
   those upstream would pollute the shared codebase. This is a judgement call; when it
   is close, ask.

## What "shared code" means

| Routes upstream | Stays here |
|---|---|
| Anything in `aroma-store-core` / `lylyrose-core` | Branding / theme visual tweaks |
| Anything in the storefront theme's shared templates | A store-specific page or campaign |
| Test suite behaviour | Host, gateway, or plugin configuration |
| A bug in code both repos share | Deploy settings, secrets, `.env` |
| Any new `ASC_` class | Local dev-environment differences |

Rule of thumb: **if the other store would want it too, it is shared.** If only this
store would want it, it can stay here — but say so explicitly rather than silently
building shared code here.

## Hard rules

- **A feature must never exist here without existing upstream first.** That is the
  failure this policy exists to prevent. The `ASC_` class counts are a standing check:
  upstream 17, here 16. If this repo's count ever exceeds upstream's, work happened in
  the wrong place.
- **Never port a red upstream test.** Green upstream is the entry condition for mirroring.
- **Never edit this repo's copy of shared code to diverge silently.** A genuine
  difference belongs in a filter or hook so it stays visible, not in a forked file.
- **Fixes route the same way as features.** A bug found in shared code is fixed
  upstream, not patched here.
- **When docs disagree, upstream wins.** This repo's copy gets corrected.

## Current standing gap

`aroma_store` leads by one feature, absent here:

- **`ASC_Flash_Sales`** (`class-flash-sales.php`) + `page-incredible-offers.php` —
  the «پیشنهادهای شگفت‌انگیز» (Incredible Offers) page, built upstream 2026-09-17.

To close the gap: build/verify it upstream, then mirror per step 4–5. It is a shared
storefront capability (a sale-product page driven by WooCommerce sale state), not a
brand-specific campaign, so it belongs in both stores.

## Related

- [UPSTREAM_RELATIONSHIP.md](UPSTREAM_RELATIONSHIP.md) — who is upstream, the rename
  map, the current divergence
- [CONTINUATION.md](CONTINUATION.md) — this project's state and handoff
- Upstream: `aroma_store/docs/FEATURE_REQUEST_POLICY.md`
